<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\Review;
use App\Models\Ecommerce\Product;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request, $productId)
    {
        $reviews = Review::where('product_id', $productId)
            ->where('is_approved', true)
            ->with(['customer:id,first_name,last_name,avatar']) // Assuming customer relation
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    public function store(Request $request, $productId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
        ]);

        $product = Product::findOrFail($productId);
        $customer = $request->user(); // Assuming authenticated customer

        $review = Review::create([
            'product_id' => $productId,
            'customer_id' => $customer ? $customer->id : 1, // Fallback for testing
            'rating' => $request->rating,
            'title' => $request->title,
            'comment' => $request->comment,
            'is_approved' => false, // Require admin approval
            'is_verified' => false, // Custom logic for verified purchase could go here
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted for approval',
            'data' => $review
        ], 201);
    }

    public function stats($productId)
    {
        $stats = Review::where('product_id', $productId)
            ->where('is_approved', true)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->get();

        $totalReviews = $stats->sum('count');
        $averageRating = $totalReviews > 0 
            ? $stats->sum(fn($s) => $s->rating * $s->count) / $totalReviews 
            : 0;

        $breakdown = collect(range(5, 1))->map(function($stars) use ($stats) {
            $found = $stats->where('rating', $stars)->first();
            return [
                'stars' => $stars,
                'count' => $found ? $found->count : 0
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'averageRating' => (float)number_format($averageRating, 1),
                'totalReviews' => (int)$totalReviews,
                'ratingBreakdown' => $breakdown
            ]
        ]);
    }
}
