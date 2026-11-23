<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BorrowResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\TransactionResource;
use App\Models\ActivityLog;
use App\Models\BorrowRecord;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function index()
    {
        Gate::authorize('dashboard:read');
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfWindow = $now->copy()->subDays(6)->startOfDay();

        $activeItems = Item::where('is_active', true);
        $totalItems = (clone $activeItems)->count();
        $totalAvailableStocks = (int) (clone $activeItems)->sum('quantity');
        $lowStockCount = (clone $activeItems)->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'minimum_stock')->count();
        $outOfStockCount = (clone $activeItems)->where('quantity', 0)->count();
        $inStockCount = (clone $activeItems)->whereColumn('quantity', '>', 'minimum_stock')->count();

        // Category breakdown
        $categoryRows = Category::withCount(['items' => fn ($q) => $q->where('is_active', true)])
            ->withSum(['items' => fn ($q) => $q->where('is_active', true)], 'quantity')
            ->get()
            ->sortByDesc('items_sum_quantity')
            ->values();

        $catTotal = max(1, (int) $categoryRows->sum('items_sum_quantity'));
        $categories = $categoryRows->map(function ($cat) use ($catTotal) {
            $qty = (int) ($cat->items_sum_quantity ?? 0);
            $low = Item::where('is_active', true)->where('category_id', $cat->id)->whereColumn('quantity', '<=', 'minimum_stock')->count();
            return [
                'name'     => $cat->name,
                'items'    => (int) $cat->items_count,
                'quantity' => $qty,
                'lowStock' => $low,
                'percent'  => (int) round(($qty / $catTotal) * 100),
            ];
        });

        // 7-day stock movement chart
        $movementRows = StockTransaction::where('date', '>=', $startOfWindow)
            ->selectRaw('DATE(date) as day, type, SUM(quantity) as quantity')
            ->groupBy('day', 'type')
            ->get();

        $typeMap = [
            'Stock In'   => 'stockIn',
            'Stock Out'  => 'stockOut',
            'Borrowed'   => 'borrowed',
            'Returned'   => 'returned',
            'Adjustment' => 'adjustment',
        ];
        $movementByDay = [];
        foreach ($movementRows as $row) {
            $key = $typeMap[$row->type] ?? null;
            if ($key) {
                $movementByDay[$row->day][$key] = (int) $row->quantity;
            }
        }

        $movement = [];
        for ($i = 0; $i < 7; $i++) {
            $dayKey = $startOfWindow->copy()->addDays($i)->toDateString();
            $movement[] = [
                'date'       => $dayKey,
                'stockIn'    => $movementByDay[$dayKey]['stockIn'] ?? 0,
                'stockOut'   => $movementByDay[$dayKey]['stockOut'] ?? 0,
                'borrowed'   => $movementByDay[$dayKey]['borrowed'] ?? 0,
                'returned'   => $movementByDay[$dayKey]['returned'] ?? 0,
                'adjustment' => $movementByDay[$dayKey]['adjustment'] ?? 0,
            ];
        }

        // Monthly stats
        $monthRows = StockTransaction::where('date', '>=', $startOfMonth)
            ->selectRaw('type, SUM(quantity) as quantity, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $activeBorrowQuery = BorrowRecord::where('status', 'Borrowed');
        $activeBorrowRecords = (clone $activeBorrowQuery)->count();
        $totalBorrowedItems = (int) (clone $activeBorrowQuery)->sum('quantity');
        $overdueBorrows = (clone $activeBorrowQuery)->where('expected_return_date', '<', $now)->count();

        $recentTransactions = StockTransaction::with(['item', 'recorder'])->orderByDesc('date')->orderByDesc('id')->limit(6)->get();
        $recentActivities = ActivityLog::orderByDesc('created_at')->orderByDesc('id')->limit(6)->get()->map(fn ($log) => [
            'id'            => (string) $log->id,
            'actorName'     => $log->actor_name,
            'actorUsername' => $log->actor_username,
            'actorRole'     => $log->actor_role,
            'action'        => $log->action,
            'entityType'    => $log->entity_type,
            'entityId'      => $log->entity_id,
            'entityLabel'   => $log->entity_label,
            'details'       => $log->details,
            'createdAt'     => $log->created_at?->toISOString(),
        ]);

        $lowStockItems = Item::with('category')
            ->where('is_active', true)
            ->whereColumn('quantity', '<=', 'minimum_stock')
            ->orderBy('quantity')
            ->limit(8)
            ->get();

        $activeBorrows = BorrowRecord::with('item')
            ->where('status', 'Borrowed')
            ->orderBy('expected_return_date')
            ->limit(5)
            ->get();

        return response()->json([
            'generatedAt' => $now->toISOString(),
            'stats'       => [
                'totalItems'           => $totalItems,
                'totalAvailableStocks' => $totalAvailableStocks,
                'lowStockItems'        => $lowStockCount,
                'outOfStockItems'      => $outOfStockCount,
                'inStockItems'         => $inStockCount,
                'totalBorrowedItems'   => $totalBorrowedItems,
                'activeBorrowRecords'  => $activeBorrowRecords,
                'overdueBorrows'       => $overdueBorrows,
                'totalCategories'      => $categories->count(),
                'stockInThisMonth'     => (int) ($monthRows['Stock In']->quantity ?? 0),
                'stockOutThisMonth'    => (int) ($monthRows['Stock Out']->quantity ?? 0),
                'borrowedThisMonth'    => (int) ($monthRows['Borrowed']->quantity ?? 0),
                'returnedThisMonth'    => (int) ($monthRows['Returned']->quantity ?? 0),
                'transactionsThisMonth'=> (int) $monthRows->sum('count'),
            ],
            'categories'         => $categories,
            'movement'           => $movement,
            'recentTransactions' => TransactionResource::collection($recentTransactions),
            'recentActivities'   => $recentActivities,
            'lowStockItems'      => ItemResource::collection($lowStockItems),
            'activeBorrows'      => BorrowResource::collection($activeBorrows),
        ]);
    }

    public function notifications()
    {
        Gate::authorize('dashboard:read');
        $now = now();

        $lowStock = Item::with('category')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'minimum_stock')
            ->orderBy('quantity')
            ->limit(6)
            ->get();

        $outOfStock = Item::with('category')
            ->where('is_active', true)
            ->where('quantity', 0)
            ->limit(6)
            ->get();

        $overdue = BorrowRecord::with('item')
            ->where('status', 'Borrowed')
            ->where('expected_return_date', '<', $now)
            ->orderBy('expected_return_date')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id'                 => (string) $r->id,
                'reference'          => $r->reference,
                'borrowerName'       => $r->borrower_name,
                'itemName'           => $r->item?->name ?? 'Item',
                'quantity'           => $r->quantity,
                'expectedReturnDate' => $r->expected_return_date?->toISOString(),
            ]);

        return response()->json([
            'lowStock'       => ItemResource::collection($lowStock),
            'outOfStock'     => ItemResource::collection($outOfStock),
            'overdueBorrows' => $overdue,
            'total'          => $lowStock->count() + $outOfStock->count() + $overdue->count(),
        ]);
    }
}