<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BorrowResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\TransactionResource;
use App\Models\BorrowRecord;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('reports:read');

        $type = $request->query('type', 'inventory');
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : null;
        $search = strtolower(trim((string) $request->query('search', '')));

        $categoryId = null;
        $categoryName = '';
        if ($request->filled('category')) {
            $cat = Category::where('name', $request->query('category'))->first();
            if (! $cat) {
                return response()->json(['message' => 'The selected category does not exist.'], 400);
            }
            $categoryId = $cat->id;
            $categoryName = $cat->name;
        }

        $base = [
            'generatedAt' => now()->toISOString(),
            'type'        => $type,
            'filters'     => ['from' => $request->query('from'), 'to' => $request->query('to'), 'category' => $categoryName, 'search' => $request->query('search', '')],
        ];

        if (in_array($type, ['inventory', 'available', 'low', 'out'], true)) {
            $query = Item::with('category')->where('is_active', true)->orderBy('name');
            if ($from) $query->where('created_at', '>=', $from);
            if ($to) $query->where('created_at', '<=', $to);
            if ($categoryId) $query->where('category_id', $categoryId);
            if ($type === 'available') $query->where('quantity', '>', 0);
            if ($type === 'low') $query->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'minimum_stock');
            if ($type === 'out') $query->where('quantity', 0);

            $items = ItemResource::collection($query->get())->resolve();
            if ($search !== '') {
                $items = array_values(array_filter($items, fn ($i) => str_contains(strtolower("{$i['name']} {$i['itemCode']} {$i['category']} {$i['location']} {$i['description']}"), $search)));
            }

            return response()->json([
                ...$base,
                'columns' => [
                    ['key' => 'itemCode', 'label' => 'Item ID'],
                    ['key' => 'name', 'label' => 'Item Name'],
                    ['key' => 'category', 'label' => 'Category'],
                    ['key' => 'quantity', 'label' => 'Quantity', 'align' => 'right'],
                    ['key' => 'unit', 'label' => 'Unit'],
                    ['key' => 'minimumStock', 'label' => 'Min. Level', 'align' => 'right'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'location', 'label' => 'Location'],
                    ['key' => 'condition', 'label' => 'Condition'],
                    ['key' => 'dateAdded', 'label' => 'Date Added'],
                ],
                'rows'    => $items,
                'summary' => [
                    'totalItems'    => count($items),
                    'totalQuantity' => array_sum(array_column($items, 'quantity')),
                    'lowStock'      => count(array_filter($items, fn ($i) => $i['status'] === 'Low Stock')),
                    'outOfStock'    => count(array_filter($items, fn ($i) => $i['status'] === 'Out of Stock')),
                ],
            ]);
        }

        if (in_array($type, ['stock-in', 'stock-out', 'transactions'], true)) {
            $query = StockTransaction::with(['item', 'recorder'])->orderByDesc('date');
            if ($from) $query->where('date', '>=', $from);
            if ($to) $query->where('date', '<=', $to);
            if ($type === 'stock-in') $query->where('type', 'Stock In');
            if ($type === 'stock-out') $query->where('type', 'Stock Out');
            if ($categoryId) $query->whereHas('item', fn ($q) => $q->where('category_id', $categoryId));

            $txns = TransactionResource::collection($query->get())->resolve();
            if ($search !== '') {
                $txns = array_values(array_filter($txns, fn ($t) => str_contains(strtolower("{$t['reference']} {$t['itemName']} {$t['itemCode']} {$t['supplier']} {$t['reason']} {$t['remarks']}"), $search)));
            }

            return response()->json([
                ...$base,
                'columns' => [
                    ['key' => 'reference', 'label' => 'Reference'],
                    ['key' => 'date', 'label' => 'Date'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'itemCode', 'label' => 'Item ID'],
                    ['key' => 'itemName', 'label' => 'Item Name'],
                    ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
                    ['key' => 'balanceAfter', 'label' => 'Balance', 'align' => 'right'],
                    ['key' => 'supplier', 'label' => 'Supplier / Source'],
                    ['key' => 'reason', 'label' => 'Reason'],
                    ['key' => 'remarks', 'label' => 'Remarks'],
                    ['key' => 'recordedBy', 'label' => 'Recorded By'],
                ],
                'rows'    => $txns,
                'summary' => [
                    'totalTransactions' => count($txns),
                    'totalQuantity'     => array_sum(array_column($txns, 'quantity')),
                    'stockIn'           => array_sum(array_map(fn ($t) => $t['type'] === 'Stock In' ? $t['quantity'] : 0, $txns)),
                    'stockOut'          => array_sum(array_map(fn ($t) => $t['type'] === 'Stock Out' ? $t['quantity'] : 0, $txns)),
                ],
            ]);
        }

        $query = BorrowRecord::with(['item', 'recorder'])->orderByDesc('date_borrowed');
        if ($from) $query->where('date_borrowed', '>=', $from);
        if ($to) $query->where('date_borrowed', '<=', $to);
        if ($categoryId) $query->whereHas('item', fn ($q) => $q->where('category_id', $categoryId));

        $borrows = BorrowResource::collection($query->get())->resolve();
        if ($search !== '') {
            $borrows = array_values(array_filter($borrows, fn ($b) => str_contains(strtolower("{$b['reference']} {$b['borrowerName']} {$b['borrowerIdNumber']} {$b['itemName']} {$b['itemCode']}"), $search)));
        }

        return response()->json([
            ...$base,
            'columns' => [
                ['key' => 'reference', 'label' => 'Reference'],
                ['key' => 'borrowerName', 'label' => 'Borrower'],
                ['key' => 'borrowerIdNumber', 'label' => 'ID Number'],
                ['key' => 'itemCode', 'label' => 'Item ID'],
                ['key' => 'itemName', 'label' => 'Item'],
                ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
                ['key' => 'dateBorrowed', 'label' => 'Date Borrowed'],
                ['key' => 'expectedReturnDate', 'label' => 'Expected Return'],
                ['key' => 'actualReturnDate', 'label' => 'Actual Return'],
                ['key' => 'displayStatus', 'label' => 'Status'],
                ['key' => 'remarks', 'label' => 'Remarks'],
            ],
            'rows'    => $borrows,
            'summary' => [
                'totalRecords'  => count($borrows),
                'totalQuantity' => array_sum(array_column($borrows, 'quantity')),
                'active'        => count(array_filter($borrows, fn ($b) => $b['displayStatus'] === 'Borrowed')),
                'overdue'       => count(array_filter($borrows, fn ($b) => $b['displayStatus'] === 'Overdue')),
                'returned'      => count(array_filter($borrows, fn ($b) => $b['displayStatus'] === 'Returned')),
            ],
        ]);
    }
}