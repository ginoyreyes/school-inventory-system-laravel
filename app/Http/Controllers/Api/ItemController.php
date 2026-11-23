<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\BorrowRecord;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockTransaction;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        Gate::authorize('inventory:read');
        $limit = min(100, max(1, (int) $request->query('limit', 12)));
        $includeArchived = $request->query('includeArchived') === 'true' && $request->user()->role === 'Administrator';

        $baseQuery = Item::with('category');
        if (! $includeArchived) {
            $baseQuery->where('is_active', true);
        }

        if ($request->filled('category')) {
            $cat = Category::where('name', $request->query('category'))->first();
            if (! $cat) {
                return response()->json([
                    'items' => [], 'total' => 0, 'page' => 1, 'pages' => 1,
                    'summary' => ['totalItems' => 0, 'totalQuantity' => 0, 'lowStock' => 0, 'outOfStock' => 0],
                    'statusCounts' => ['In Stock' => 0, 'Low Stock' => 0, 'Out of Stock' => 0],
                ]);
            }
            $baseQuery->where('category_id', $cat->id);
        }

        if ($request->filled('location')) $baseQuery->where('location', $request->query('location'));
        if ($request->filled('condition')) $baseQuery->where('condition', $request->query('condition'));

        if ($request->filled('search')) {
            $s = '%' . $request->query('search') . '%';
            $baseQuery->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('item_code', 'like', $s)
                  ->orWhere('description', 'like', $s)
                  ->orWhere('location', 'like', $s);
            });
        }

        // Status counts before applying status filter
        $countClone = clone $baseQuery;
        $statusCounts = [
            'In Stock'     => (clone $countClone)->whereColumn('quantity', '>', 'minimum_stock')->count(),
            'Low Stock'    => (clone $countClone)->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'minimum_stock')->count(),
            'Out of Stock' => (clone $countClone)->where('quantity', 0)->count(),
        ];

        if ($request->filled('status')) {
            match ($request->query('status')) {
                'Out of Stock' => $baseQuery->where('quantity', 0),
                'Low Stock'    => $baseQuery->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'minimum_stock'),
                'In Stock'     => $baseQuery->whereColumn('quantity', '>', 'minimum_stock'),
                default        => null,
            };
        }

        $summaryClone = clone $baseQuery;
        $summary = [
            'totalItems'    => (clone $summaryClone)->count(),
            'totalQuantity' => (int) (clone $summaryClone)->sum('quantity'),
            'lowStock'      => (clone $summaryClone)->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'minimum_stock')->count(),
            'outOfStock'    => (clone $summaryClone)->where('quantity', 0)->count(),
        ];

        $sortMap = [
            'name'         => 'name',
            'quantity'     => 'quantity',
            'minimumStock' => 'minimum_stock',
            'dateAdded'    => 'created_at',
            'lastUpdated'  => 'updated_at',
        ];
        $sortCol = $sortMap[$request->query('sort')] ?? 'name';
        $sortDir = $request->query('order') === 'desc' ? 'desc' : 'asc';

        $paginated = $baseQuery->orderBy($sortCol, $sortDir)->paginate($limit);

        return response()->json([
            'items'        => ItemResource::collection($paginated->items()),
            'total'        => $paginated->total(),
            'page'         => $paginated->currentPage(),
            'pages'        => max(1, $paginated->lastPage()),
            'summary'      => $summary,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function show(Item $item)
    {
        Gate::authorize('inventory:read');
        return response()->json(['item' => new ItemResource($item->load('category'))]);
    }

    public function store(Request $request)
    {
        Gate::authorize('inventory:create');
        $settings = SettingsController::current();

        $data = $request->validate([
            'name'         => ['required', 'string', 'min:2', 'max:120'],
            'categoryId'   => ['required', 'exists:categories,id'],
            'description'  => ['nullable', 'string', 'max:500'],
            'quantity'     => ['required', 'integer', 'min:0', 'max:1000000'],
            'unit'         => ['required', 'string', 'max:20'],
            'minimumStock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'location'     => ['nullable', 'string', 'max:80'],
            'condition'    => ['nullable', 'string', 'max:50'],
        ]);

        $exists = Item::where('name', trim($data['name']))
            ->where('category_id', $data['categoryId'])
            ->where('is_active', true)
            ->exists();
        if ($exists) {
            return response()->json(['message' => "\"{$data['name']}\" already exists in this category."], 409);
        }

        return DB::transaction(function () use ($data, $settings, $request) {
            $item = Item::create([
                'name'          => trim($data['name']),
                'category_id'   => $data['categoryId'],
                'description'   => $data['description'] ?? '',
                'quantity'      => $data['quantity'],
                'unit'          => $data['unit'],
                'minimum_stock' => $data['minimumStock'] ?? $settings->low_stock_threshold,
                'location'      => $data['location'] ?? '',
                'condition'     => $data['condition'] ?? ($settings->conditions[0] ?? 'Good'),
                'created_by'    => $request->user()->id,
                'updated_by'    => $request->user()->id,
            ]);

            if ($item->quantity > 0) {
                StockTransaction::create([
                    'type'          => 'Stock In',
                    'item_id'       => $item->id,
                    'quantity'      => $item->quantity,
                    'balance_after' => $item->quantity,
                    'date'          => now(),
                    'supplier'      => 'Initial stock',
                    'remarks'       => 'Opening balance recorded when the item was added.',
                    'recorded_by'   => $request->user()->id,
                ]);
            }

            $item->load('category');
            $this->logActivity($request, 'Added inventory item', 'Item', (string) $item->id, "{$item->item_code} · {$item->name}", "Initial quantity {$item->quantity} {$item->unit}");

            return response()->json([
                'message' => "\"{$item->name}\" was added to the inventory.",
                'item'    => new ItemResource($item),
            ], 201);
        });
    }

    public function update(Request $request, Item $item)
    {
        Gate::authorize('inventory:update');
        if ($request->has('quantity')) {
            return response()->json(['message' => 'Use Stock Transactions to adjust item quantity.'], 400);
        }

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'min:2', 'max:120'],
            'categoryId'   => ['sometimes', 'exists:categories,id'],
            'description'  => ['nullable', 'string', 'max:500'],
            'unit'         => ['sometimes', 'string', 'max:20'],
            'minimumStock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'location'     => ['nullable', 'string', 'max:80'],
            'condition'    => ['sometimes', 'string', 'max:50'],
        ]);

        if (isset($data['categoryId'])) $item->category_id = $data['categoryId'];
        if (isset($data['name'])) $item->name = trim($data['name']);
        if (array_key_exists('description', $data)) $item->description = $data['description'] ?? '';
        if (isset($data['unit'])) $item->unit = $data['unit'];
        if (isset($data['minimumStock'])) $item->minimum_stock = $data['minimumStock'];
        if (array_key_exists('location', $data)) $item->location = $data['location'] ?? '';
        if (isset($data['condition'])) $item->condition = $data['condition'];
        $item->updated_by = $request->user()->id;
        $item->save();

        $this->logActivity($request, 'Updated inventory item', 'Item', (string) $item->id, "{$item->item_code} · {$item->name}");

        return response()->json([
            'message' => 'Item updated.',
            'item'    => new ItemResource($item->load('category')),
        ]);
    }

    public function destroy(Request $request, Item $item)
    {
        Gate::authorize('inventory:archive');
        $activeBorrows = BorrowRecord::where('item_id', $item->id)->where('status', 'Borrowed')->count();
        if ($activeBorrows > 0) {
            return response()->json(['message' => 'This item is currently borrowed. Record its return before archiving.'], 409);
        }

        $item->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        $this->logActivity($request, 'Archived inventory item', 'Item', (string) $item->id, "{$item->item_code} · {$item->name}");

        return response()->json(['message' => "\"{$item->name}\" was archived. Its history is preserved."]);
    }

    public function restore(Request $request, Item $item)
    {
        Gate::authorize('inventory:archive');
        $item->update(['is_active' => true, 'updated_by' => $request->user()->id]);
        $this->logActivity($request, 'Restored inventory item', 'Item', (string) $item->id, "{$item->item_code} · {$item->name}");

        return response()->json([
            'message' => "\"{$item->name}\" was restored to the inventory.",
            'item'    => new ItemResource($item->load('category')),
        ]);
    }

    public function metaUnits()
    {
        Gate::authorize('inventory:read');
        $s = SettingsController::current();
        return response()->json(['units' => $s->units, 'conditions' => $s->conditions]);
    }
}