<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use LogsActivity;

    public function index()
    {
        Gate::authorize('categories:read');
        $categories = Category::withCount('items')
            ->withSum('items', 'quantity')
            ->orderBy('name')
            ->get();

        return response()->json(['categories' => CategoryResource::collection($categories)]);
    }

    public function store(Request $request)
    {
        Gate::authorize('categories:manage');
        $data = $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:60', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:240'],
        ]);

        $category = Category::create([
            'name'        => trim($data['name']),
            'description' => $data['description'] ?? '',
            'created_by'  => $request->user()->id,
        ]);

        $this->logActivity($request, 'Created category', 'Category', (string) $category->id, $category->name);

        return response()->json([
            'message'  => 'Category created.',
            'category' => new CategoryResource($category),
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        Gate::authorize('categories:manage');
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'min:2', 'max:60', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:240'],
        ]);

        if (isset($data['name'])) $category->name = trim($data['name']);
        if (array_key_exists('description', $data)) $category->description = $data['description'] ?? '';
        $category->save();

        $this->logActivity($request, 'Updated category', 'Category', (string) $category->id, $category->name);

        return response()->json([
            'message'  => 'Category updated.',
            'category' => new CategoryResource($category),
        ]);
    }

    public function destroy(Request $request, Category $category)
    {
        Gate::authorize('categories:manage');
        $count = $category->items()->count();
        if ($count > 0) {
            return response()->json([
                'message' => "This category still has {$count} inventory item(s). Reassign or archive them first.",
            ], 409);
        }

        $name = $category->name;
        $id = (string) $category->id;
        $category->delete();

        $this->logActivity($request, 'Deleted category', 'Category', $id, $name);

        return response()->json(['message' => 'Category deleted.']);
    }
}