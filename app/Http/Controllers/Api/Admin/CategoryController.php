<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return CategoryResource::collection(Category::whereNull('parent_id')->with('children')->withCount('products')->orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $data['slug'] = Category::uniqueSlug($data['name']);
        $category = Category::create($data);
        AuditLogger::record($request, 'category.created', $category, null, $category->only(['name', 'parent_id']));
        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(Request $request, Category $category)
    {
        $old = $category->only(['name', 'parent_id', 'is_active']);
        $data = $this->validated($request, $category);
        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = Category::uniqueSlug($data['name'], $category->id);
        }
        $category->update($data);
        AuditLogger::record($request, 'category.updated', $category, $old, $category->only(array_keys($old)));
        return new CategoryResource($category->fresh());
    }

    public function destroy(Request $request, Category $category)
    {
        abort_if($category->products()->exists() || $category->children()->exists(), 422, 'Move or delete the category\'s products and subcategories first.');
        AuditLogger::record($request, 'category.deleted', $category, $category->only(['name']));
        $category->delete();
        return response()->noContent();
    }

    private function validated(Request $request, ?Category $current): array
    {
        $req = $current ? 'sometimes' : 'required';
        $data = $request->validate([
            'name' => [$req, 'string', 'min:2', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        foreach (['name', 'description'] as $f) {
            if (isset($data[$f])) $data[$f] = trim(strip_tags($data[$f]));
        }
        if ($current && ! empty($data['parent_id']) && in_array((int) $data['parent_id'], $current->descendantIds(), true)) {
            abort(422, 'A category cannot be its own parent or descendant.');
        }
        return $data;
    }
}
