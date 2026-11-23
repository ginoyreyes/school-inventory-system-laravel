<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BorrowResource;
use App\Http\Resources\TransactionResource;
use App\Models\BorrowRecord;
use App\Models\Item;
use App\Models\StockTransaction;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BorrowController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        Gate::authorize('borrows:read');
        $limit = min(100, max(1, (int) $request->query('limit', 12)));

        $query = BorrowRecord::with(['item', 'recorder', 'returner']);

        if ($request->filled('from')) {
            $query->where('date_borrowed', '>=', Carbon::parse($request->query('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('date_borrowed', '<=', Carbon::parse($request->query('to'))->endOfDay());
        }

        $status = $request->query('status');
        if ($status === 'Borrowed') {
            $query->where('status', 'Borrowed');
        } elseif ($status === 'Returned') {
            $query->where('status', 'Returned');
        } elseif ($status === 'Overdue') {
            $query->where('status', 'Borrowed')->where('expected_return_date', '<', now());
        }

        if ($request->filled('search')) {
            $s = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($s) {
                $q->where('reference', 'like', $s)
                  ->orWhere('borrower_name', 'like', $s)
                  ->orWhere('borrower_id_number', 'like', $s)
                  ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', $s)->orWhere('item_code', 'like', $s));
            });
        }

        $summaryRows = (clone $query)
            ->reorder()
            ->selectRaw('status, COUNT(*) as count, SUM(quantity) as quantity')
            ->groupBy('status')
            ->get();

        $summary = ['Borrowed' => ['count' => 0, 'quantity' => 0], 'Returned' => ['count' => 0, 'quantity' => 0]];
        foreach ($summaryRows as $row) {
            $summary[$row->status] = [
                'count'    => (int) $row->count,
                'quantity' => (int) $row->quantity,
            ];
        }

        $overdueCount = BorrowRecord::where('status', 'Borrowed')
            ->where('expected_return_date', '<', now())
            ->count();

        $paginated = $query->orderByDesc('date_borrowed')->orderByDesc('id')->paginate($limit);

        return response()->json([
            'borrows'      => BorrowResource::collection($paginated->items()),
            'total'        => $paginated->total(),
            'page'         => $paginated->currentPage(),
            'pages'        => max(1, $paginated->lastPage()),
            'summary'      => $summary,
            'overdueCount' => $overdueCount,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('borrows:create');

        $data = $request->validate([
            'borrowerName'       => ['required', 'string', 'min:2', 'max:100'],
            'borrowerIdNumber'   => ['required', 'string', 'min:2', 'max:40'],
            'itemId'             => ['required', 'exists:items,id'],
            'quantity'           => ['required', 'integer', 'min:1', 'max:1000000'],
            'dateBorrowed'       => ['nullable', 'date'],
            'expectedReturnDate' => ['required', 'date'],
            'remarks'            => ['nullable', 'string', 'max:300'],
        ]);

        $dateBorrowed = ! empty($data['dateBorrowed']) ? Carbon::parse($data['dateBorrowed']) : now();
        $expectedReturn = Carbon::parse($data['expectedReturnDate']);

        if ($expectedReturn->lt($dateBorrowed->copy()->startOfDay())) {
            return response()->json(['message' => 'The expected return date cannot be earlier than the borrowing date.'], 400);
        }

        return DB::transaction(function () use ($data, $dateBorrowed, $expectedReturn, $request) {
            $item = Item::where('id', $data['itemId'])->lockForUpdate()->firstOrFail();

            if (! $item->is_active) {
                return response()->json(['message' => 'This item is archived and cannot be borrowed.'], 400);
            }
            if ($item->quantity < $data['quantity']) {
                return response()->json([
                    'message' => "Cannot borrow {$data['quantity']} {$item->unit} of {$item->name}. Only {$item->quantity} available.",
                ], 409);
            }

            $item->decrement('quantity', $data['quantity']);
            $item->update(['updated_by' => $request->user()->id]);

            $borrow = BorrowRecord::create([
                'borrower_name'        => trim($data['borrowerName']),
                'borrower_id_number'   => trim($data['borrowerIdNumber']),
                'item_id'              => $item->id,
                'quantity'             => $data['quantity'],
                'date_borrowed'        => $dateBorrowed,
                'expected_return_date' => $expectedReturn,
                'status'               => 'Borrowed',
                'remarks'              => trim($data['remarks'] ?? ''),
                'recorded_by'          => $request->user()->id,
            ]);

            StockTransaction::create([
                'type'          => 'Borrowed',
                'item_id'       => $item->id,
                'quantity'      => $data['quantity'],
                'balance_after' => $item->quantity,
                'date'          => $dateBorrowed,
                'reason'        => "Borrowed by {$borrow->borrower_name}",
                'remarks'       => "Borrow reference {$borrow->reference}. Expected return {$expectedReturn->toDateString()}.",
                'recorded_by'   => $request->user()->id,
            ]);

            $borrow->load(['item', 'recorder']);

            $this->logActivity(
                $request,
                'Recorded borrowed item',
                'Borrow',
                (string) $borrow->id,
                "{$borrow->reference} · {$item->name}",
                "{$borrow->borrower_name} ({$borrow->borrower_id_number}) borrowed {$borrow->quantity} {$item->unit}."
            );

            return response()->json([
                'message' => "Borrowing recorded for {$borrow->borrower_name}. {$item->name} now has {$item->quantity} {$item->unit} available.",
                'borrow'  => new BorrowResource($borrow),
            ], 201);
        });
    }

    public function returnItem(Request $request, BorrowRecord $borrow)
    {
        Gate::authorize('borrows:update');

        if ($borrow->status === 'Returned') {
            return response()->json(['message' => 'This item has already been returned.'], 409);
        }

        return DB::transaction(function () use ($borrow, $request) {
            $item = Item::where('id', $borrow->item_id)->lockForUpdate()->firstOrFail();
            $item->increment('quantity', $borrow->quantity);
            $item->update(['updated_by' => $request->user()->id]);

            $now = now();
            $borrow->update([
                'status'             => 'Returned',
                'actual_return_date' => $now,
                'returned_by'        => $request->user()->id,
            ]);

            $transaction = StockTransaction::create([
                'type'          => 'Returned',
                'item_id'       => $item->id,
                'quantity'      => $borrow->quantity,
                'balance_after' => $item->quantity,
                'date'          => $now,
                'reason'        => "Returned by {$borrow->borrower_name}",
                'remarks'       => "Borrow reference {$borrow->reference}.",
                'recorded_by'   => $request->user()->id,
            ]);

            $borrow->load(['item', 'recorder', 'returner']);
            $transaction->load(['item', 'recorder']);

            $this->logActivity(
                $request,
                'Recorded returned item',
                'Borrow',
                (string) $borrow->id,
                "{$borrow->reference} · {$item->name}",
                "{$borrow->borrower_name} returned {$borrow->quantity} {$item->unit}. New balance: {$item->quantity}."
            );

            return response()->json([
                'message'     => "Return recorded. {$item->name} now has {$item->quantity} {$item->unit} available.",
                'borrow'      => new BorrowResource($borrow),
                'transaction' => new TransactionResource($transaction),
            ]);
        });
    }
}