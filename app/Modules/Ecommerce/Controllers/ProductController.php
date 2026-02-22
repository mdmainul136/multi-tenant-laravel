<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::where('is_active', true)
            ->when($request->category, function ($query, $category) {
                return $query->where('category', $category);
            })
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:tenant_dynamic.ec_products,sku',
            'price' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'stock_quantity' => 'required|integer|min:0',
        ]);

        $dto = \App\Modules\Ecommerce\DTOs\ProductDTO::fromRequest($request->all());
        $product = app(\App\Modules\Ecommerce\Actions\StoreProductAction::class)->execute($dto);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'string|max:255',
            'sku' => 'string|max:100|unique:tenant_dynamic.ec_products,sku,' . $id,
            'price' => 'numeric|min:0',
            'stock_quantity' => 'integer|min:0',
        ]);

        $dto = \App\Modules\Ecommerce\DTOs\ProductDTO::fromRequest($request->all());
        $product = app(\App\Modules\Ecommerce\Actions\UpdateProductAction::class)->execute((int)$id, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product
        ]);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}
