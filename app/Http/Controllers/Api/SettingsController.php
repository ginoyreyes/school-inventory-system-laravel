<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Setting;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    use LogsActivity;

    public static function current(): Setting
    {
        return Setting::first() ?? Setting::create([
            'low_stock_threshold' => 10,
            'borrow_period_days'  => 7,
            'units'               => ['pcs', 'box', 'set', 'ream', 'pack', 'unit', 'bottle', 'meter', 'kg'],
            'conditions'          => ['New', 'Good', 'Fair', 'Needs Repair', 'Retired'],
        ]);
    }

    private function dto(Setting $s): array
    {
        return [
            'lowStockThreshold' => $s->low_stock_threshold,
            'borrowPeriodDays'  => $s->borrow_period_days,
            'units'             => $s->units,
            'conditions'        => $s->conditions,
            'updatedAt'         => $s->updated_at?->toISOString(),
        ];
    }

    public function show()
    {
        Gate::authorize('inventory:read');
        return response()->json(['settings' => $this->dto(self::current())]);
    }

    public function update(Request $request)
    {
        Gate::authorize('settings:manage');
        $data = $request->validate([
            'lowStockThreshold' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'borrowPeriodDays'  => ['sometimes', 'integer', 'min:1', 'max:180'],
            'units'             => ['sometimes', 'array', 'min:1', 'max:50'],
            'units.*'           => ['string', 'min:1', 'max:20'],
            'conditions'        => ['sometimes', 'array', 'min:1', 'max:50'],
            'conditions.*'      => ['string', 'min:1', 'max:50'],
        ]);

        $settings = self::current();
        if (isset($data['lowStockThreshold'])) $settings->low_stock_threshold = $data['lowStockThreshold'];
        if (isset($data['borrowPeriodDays'])) $settings->borrow_period_days = $data['borrowPeriodDays'];
        if (isset($data['units'])) $settings->units = array_values(array_unique(array_map('trim', $data['units'])));
        if (isset($data['conditions'])) $settings->conditions = array_values(array_unique(array_map('trim', $data['conditions'])));
        $settings->updated_by = $request->user()->id;
        $settings->save();

        $this->logActivity($request, 'Updated system settings', 'Settings', 'system', 'Inventory settings');

        return response()->json(['message' => 'Settings saved.', 'settings' => $this->dto($settings)]);
    }

    public function applyThreshold(Request $request)
    {
        Gate::authorize('settings:manage');
        $settings = self::current();
        $updated = Item::where('is_active', true)->update(['minimum_stock' => $settings->low_stock_threshold]);

        $this->logActivity($request, 'Applied low-stock threshold', 'Settings', 'system', 'Inventory settings', "Minimum stock set to {$settings->low_stock_threshold} for {$updated} item(s).");

        return response()->json([
            'message' => "Minimum stock level updated to {$settings->low_stock_threshold} for {$updated} item(s).",
            'updated' => $updated,
        ]);
    }
}