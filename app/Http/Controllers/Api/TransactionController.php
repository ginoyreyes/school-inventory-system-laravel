<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Item;
use App\Models\StockTransaction;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        Gate::authorize('transactions:read');
        $limit = min(100, max(1, (int) $request->query('limit', 12)));

        $query = StockTransaction::with(['item', 'recorder']);

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('from')) {
            $query->where('date', '>=', Carbon::parse($request->query('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', Carbon::parse($request->query('to'))->endOfDay());
        }
        if ($request->filled('search')) {
            $s = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($s) {
                $q->where('reference', 'like', $s)
                  ->orWhere('supplier', 'like', $s)
                  ->orWhere('reason', 'like', $s)
                  ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', $s)->orWhere('item_code', 'like', $s))
                  ->orWhereHas('recorder', fn ($uq) => $uq->where('name', 'like', $s));
            });
        }

        $summaryRows = (clone $query)
            ->reorder()
            ->selectRaw('type, SUM(quantity) as quantity, COUNT(*) as count')
            ->groupBy('type')
            ->get();

        $summary = [];
        foreach ($summaryRows as $row) {
            $summary[$row->type] = [
                'quantity' => (int) $row->quantity,
                'count'    => (int) $row->count,
            ];
        }

        $paginated = $query->orderByDesc('date')->orderByDesc('id')->paginate($limit);

        return response()->json([
            'transactions' => TransactionResource::collection($paginated->items()),
            'total'        => $paginated->total(),
            'page'         => $paginated->currentPage(),
            'pages'        => max(1, $paginated->lastPage()),
            'summary'      => $summary,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('transactions:create');

        $data = $request->validate([
            'type'     => ['required', 'in:Stock In,Stock Out,Adjustment'],
            'itemId'   => ['required', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'date'     => ['nullable', 'date'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'reason'   => ['nullable', 'string', 'max:160'],
            'remarks'  => ['nullable', 'string', 'max:300'],
        ]);

        $type = $data['type'];
        $qty = (int) $data['quantity'];
        $reason = trim($data['reason'] ?? '');
        $supplier = trim($data['supplier'] ?? '');
        $remarks = trim($data['remarks'] ?? '');
        $txnDate = ! empty($data['date']) ? Carbon::parse($data['date']) : now();

        if ($type === 'Stock Out' && $reason === '') {
            return response()->json(['message' => 'Please provide a reason for releasing the stock.'], 400);
        }
        if ($type === 'Adjustment') {
            if ($request->user()->role !== 'Administrator') {
                return response()->json(['message' => 'Only administrators can adjust stock quantities.'], 403);
            }
            if ($reason === '') {
                return response()->json(['message' => 'Please provide a reason for the stock adjustment.'], 400);
            }
        } elseif ($qty < 1) {
            return response()->json(['message' => 'Quantity must be at least 1.'], 400);
        }

        return DB::transaction(function () use ($type, $data, $qty, $reason, $supplier, $remarks, $txnDate, $request) {
            $item = Item::where('id', $data['itemId'])->lockForUpdate()->firstOrFail();

            if (! $item->is_active) {
                return response()->json(['message' => 'This item is archived and cannot receive new transactions.'], 400);
            }

            $oldQty = $item->quantity;

            if ($type === 'Stock In') {
                $item->quantity += $qty;
            } elseif ($type === 'Stock Out') {
                if ($item->quantity < $qty) {
                    return response()->json([
                        'message' => "Insufficient stock for {$item->name}. Available quantity: {$item->quantity} {$item->unit}.",
                    ], 409);
                }
                $item->quantity -= $qty;
            } else {
                // Adjustment sets counted quantity to an absolute value (lower or equal handled cleanly)
                if ($qty === $oldQty) {
                    return response()->json(['message' => 'The counted quantity matches the current stock. No adjustment needed.'], 400);
                }
                if ($qty > $oldQty) {
                    return response()->json([
                        'message' => "Cannot set quantity to {$qty} above current stock ({$oldQty}). Use Stock In instead.",
                    ], 409);
                }
                $item->quantity = $qty;
            }

            $item->updated_by = $request->user()->id;
            $item->save();

            $delta = abs($item->quantity - $oldQty);

            $transaction = StockTransaction::create([
                'type'          => $type,
                'item_id'       => $item->id,
                'quantity'      => $type === 'Adjustment' ? $delta : $qty,
                'balance_after' => $item->quantity,
                'date'          => $txnDate,
                'supplier'      => $type === 'Stock In' ? $supplier : '',
                'reason'        => $type === 'Stock In' ? '' : $reason,
                'remarks'       => $type === 'Adjustment'
                    ? "Counted quantity set to {$qty} (was {$oldQty}). " . ($remarks ?: $reason)
                    : $remarks,
                'recorded_by'   => $request->user()->id,
            ]);

            $transaction->load(['item', 'recorder']);

            $this->logActivity(
                $request,
                'Recorded ' . strtolower($type),
                'Item',
                (string) $item->id,
                "{$item->item_code} · {$item->name}",
                "{$type} of {$transaction->quantity} {$item->unit}. New balance: {$item->quantity}."
            );

            $msg = match ($type) {
                'Stock In'  => "Stock in recorded. {$item->name} now has {$item->quantity} {$item->unit}.",
                'Stock Out' => "Stock out recorded. {$item->name} now has {$item->quantity} {$item->unit}.",
                default     => "Stock adjusted. {$item->name} now has {$item->quantity} {$item->unit}.",
            };

            return response()->json([
                'message'     => $msg,
                'transaction' => new TransactionResource($transaction),
                'item'        => [
                    'id'       => (string) $item->id,
                    'name'     => $item->name,
                    'quantity' => $item->quantity,
                    'unit'     => $item->unit,
                ],
            ], 201);
        });
    }
}