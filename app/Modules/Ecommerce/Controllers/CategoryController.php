<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)
            ->withCount('products')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:tenant_dynamic.ec_categories,id',
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($request->name) . '-' . uniqid();

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 21);
    }

    public function show($id)
    {
        $category = Category::with('children')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'string|max:255',
            'parent_id' => 'nullable|exists:tenant_dynamic.ec_categories,id',
        ]);

        $category->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        
        if ($category->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with associated products'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
