<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaceResource;
use App\Models\Item;
use App\Models\Place;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PlaceController extends Controller
{
    use LogsActivity;

    public function index()
    {
        Gate::authorize('places:read');
        $stats = Item::selectRaw('location, COUNT(*) as item_count, SUM(quantity) as total_quantity')
            ->groupBy('location')
            ->get()
            ->keyBy('location');

        $places = Place::orderBy('name')->get()->map(function (Place $place) use ($stats) {
            $place->item_count = $stats[$place->name]->item_count ?? 0;
            $place->total_quantity = $stats[$place->name]->total_quantity ?? 0;
            return $place;
        });

        return response()->json(['places' => PlaceResource::collection($places)]);
    }

    public function store(Request $request)
    {
        Gate::authorize('places:manage');
        $data = $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:80', 'unique:places,name'],
            'description' => ['nullable', 'string', 'max:240'],
            'area'        => ['nullable', 'string', 'max:60'],
        ]);

        $place = Place::create([
            'name'        => trim($data['name']),
            'description' => $data['description'] ?? '',
            'area'        => trim($data['area'] ?? ''),
            'created_by'  => $request->user()->id,
        ]);

        $this->logActivity($request, 'Created place', 'Place', (string) $place->id, $place->name);

        return response()->json([
            'message' => 'Place created.',
            'place'   => new PlaceResource($place),
        ], 201);
    }

    public function update(Request $request, Place $place)
    {
        Gate::authorize('places:manage');
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'min:2', 'max:80', Rule::unique('places', 'name')->ignore($place->id)],
            'description' => ['nullable', 'string', 'max:240'],
            'area'        => ['nullable', 'string', 'max:60'],
        ]);

        $oldName = $place->name;
        $moved = 0;

        if (isset($data['name']) && trim($data['name']) !== $oldName) {
            $place->name = trim($data['name']);
            $moved = Item::where('location', $oldName)->update(['location' => $place->name]);
        }
        if (array_key_exists('description', $data)) $place->description = $data['description'] ?? '';
        if (array_key_exists('area', $data)) $place->area = trim($data['area'] ?? '');
        $place->save();

        $this->logActivity($request, 'Updated place', 'Place', (string) $place->id, $place->name);

        return response()->json([
            'message' => $moved ? "Place updated. {$moved} item(s) now use \"{$place->name}\"." : 'Place updated.',
            'place'   => new PlaceResource($place),
        ]);
    }

    public function destroy(Request $request, Place $place)
    {
        Gate::authorize('places:manage');
        $count = Item::where('location', $place->name)->count();
        if ($count > 0) {
            return response()->json([
                'message' => "This place still holds {$count} inventory item(s). Move them first.",
            ], 409);
        }

        $name = $place->name;
        $id = (string) $place->id;
        $place->delete();

        $this->logActivity($request, 'Deleted place', 'Place', $id, $name);

        return response()->json(['message' => 'Place deleted.']);
    }
}