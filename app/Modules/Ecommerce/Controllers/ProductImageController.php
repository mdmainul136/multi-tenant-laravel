<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\{ProductImage, Product};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    /**
     * GET /ecommerce/products/{productId}/images
     * List all gallery images for a product
     */
    public function index($productId)
    {
        Product::findOrFail($productId);  // 404 guard

        $images = ProductImage::where('product_id', $productId)
                              ->ordered()
                              ->get();

        return response()->json(['success' => true, 'data' => $images]);
    }

    /**
     * POST /ecommerce/products/{productId}/images
     * Upload one or more images to a product gallery
     */
    public function store(Request $request, $productId)
    {
        Product::findOrFail($productId);

        $request->validate([
            'images'          => 'required|array|min:1|max:20',
            'images.*'        => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB
            'alt_text'        => 'nullable|string|max:255',
            'set_first_primary'=> 'nullable|boolean',
        ]);

        $existingCount = ProductImage::where('product_id', $productId)->count();
        $hasPrimary    = ProductImage::where('product_id', $productId)->where('is_primary', true)->exists();

        $created  = [];
        $uploaded = 0;

        foreach ($request->file('images') as $index => $file) {
            $path = $file->store("product-gallery/{$productId}", 'public');
            $url  = Storage::disk('public')->url($path);

            $isPrimary = !$hasPrimary && $index === 0 && ($request->boolean('set_first_primary', true));

            $image = ProductImage::create([
                'product_id'  => $productId,
                'url'         => $url,
                'disk'        => 'public',
                'path'        => $path,
                'alt_text'    => $request->alt_text,
                'sort_order'  => $existingCount + $uploaded,
                'is_primary'  => $isPrimary,
                'file_size'   => $file->getSize(),
                'width'       => null,   // Can be computed with Intervention Image if installed
                'height'      => null,
                'mime_type'   => $file->getMimeType(),
            ]);

            if ($isPrimary) $hasPrimary = true;
            $created[] = $image;
            $uploaded++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$uploaded} image(s) uploaded",
            'data'    => $created,
        ], 201);
    }

    /**
     * PUT /ecommerce/products/{productId}/images/{imageId}
     * Update metadata (alt text, title) of an image
     */
    public function update(Request $request, $productId, $imageId)
    {
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);

        $image->update($request->validate([
            'alt_text' => 'nullable|string|max:255',
            'title'    => 'nullable|string|max:255',
        ]));

        return response()->json(['success' => true, 'data' => $image->fresh()]);
    }

    /**
     * DELETE /ecommerce/products/{productId}/images/{imageId}
     * Remove an image from the gallery
     */
    public function destroy($productId, $imageId)
    {
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);

        // Delete from storage
        if ($image->disk === 'public' && $image->path) {
            Storage::disk('public')->delete($image->path);
        }

        $wasPrimary = $image->is_primary;
        $image->delete();

        // If the deleted image was primary, promote the next image
        if ($wasPrimary) {
            $next = ProductImage::where('product_id', $productId)->ordered()->first();
            $next?->update(['is_primary' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Image deleted']);
    }

    /**
     * POST /ecommerce/products/{productId}/images/{imageId}/primary
     * Set an image as the product's primary / featured image
     */
    public function setPrimary($productId, $imageId)
    {
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);
        $image->setAsPrimary();

        return response()->json(['success' => true, 'message' => 'Primary image updated']);
    }

    /**
     * POST /ecommerce/products/{productId}/images/reorder
     * Reorder gallery images
     * Body: { "ids": [3, 1, 5, 2] } — ordered array of image IDs
     */
    public function reorder(Request $request, $productId)
    {
        Product::findOrFail($productId);

        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:tenant_dynamic.ec_product_images,id',
        ]);

        ProductImage::reorder($request->ids);

        return response()->json([
            'success' => true,
            'message' => 'Gallery order updated',
            'data'    => ProductImage::where('product_id', $productId)->ordered()->get(),
        ]);
    }

    /**
     * POST /ecommerce/products/{productId}/images/url
     * Add an image from a remote URL (no file upload)
     */
    public function addFromUrl(Request $request, $productId)
    {
        Product::findOrFail($productId);

        $request->validate([
            'url'       => 'required|url|max:500',
            'alt_text'  => 'nullable|string|max:255',
            'is_primary'=> 'nullable|boolean',
        ]);

        $existingCount = ProductImage::where('product_id', $productId)->count();
        $hasPrimary    = ProductImage::where('product_id', $productId)->where('is_primary', true)->exists();

        $isPrimary = $request->boolean('is_primary') && !$hasPrimary;

        if ($isPrimary) {
            ProductImage::where('product_id', $productId)->update(['is_primary' => false]);
        }

        $image = ProductImage::create([
            'product_id'  => $productId,
            'url'         => $request->url,
            'disk'        => 'external',
            'path'        => null,
            'alt_text'    => $request->alt_text,
            'sort_order'  => $existingCount,
            'is_primary'  => $isPrimary,
        ]);

        return response()->json(['success' => true, 'data' => $image], 201);
    }
}
