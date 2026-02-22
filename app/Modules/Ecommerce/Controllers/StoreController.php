<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\Product;
use App\Models\Ecommerce\Category;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * List all active products for the storefront
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with('categoryData')->where('is_active', true);

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Filter by featured
            if ($request->has('featured')) {
                $query->where('is_featured', true);
            }

            // Search
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $products = $query->latest()->paginate($request->get('limit', 12));

            return response()->json([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get product details by slug or ID
     */
    public function show($identifier)
    {
        try {
            $product = Product::where('is_active', true)
                ->where(function($query) use ($identifier) {
                    $query->where('id', $identifier)
                          ->orWhere('slug', $identifier);
                })
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching product details'
            ], 500);
        }
    }

    /**
     * List all active categories
     */
    public function categories()
    {
        try {
            $cacheKey = 'categories_' . request()->header('X-Tenant-ID', 'default');
            $categories = \Cache::remember($cacheKey, 600, function() {
                return Category::where('is_active', true)
                    ->orderBy('display_order', 'asc')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching categories'
            ], 500);
        }
    }

    /**
     * Get featured products
     */
    public function featured()
    {
        try {
            $products = Product::where('is_active', true)
                ->where('is_featured', true)
                ->limit(8)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching featured products'
            ], 500);
        }
    }
}
