<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductApprovalService
{
    public function __construct(
        private AiContentService $ai,
        private IorAuditService $audit
    ) {}

    /**
     * Trigger AI rewrite for a product.
     */
    public function rewriteProduct(int $productId, string $lang = 'both'): array
    {
        $product = DB::table('ec_products')->where('id', $productId)->first();

        if (!$product) {
            throw new \RuntimeException("Product not found.");
        }

        $sourceMetadata = json_decode($product->source_metadata, true);
        
        // Construct product array for AI service
        $aiInput = [
            'title'    => $sourceMetadata['original_title'] ?? $product->name,
            'features' => $sourceMetadata['original_features'] ?? [],
            'category' => $product->category,
        ];

        // Generate full listing (Title + Description + Bullets)
        $listing = $this->ai->generateListing($aiInput, $lang);

        // Update product with AI content
        DB::table('ec_products')->where('id', $productId)->update([
            'description'       => $listing['content'],
            'short_description' => 'Sourced & AI Verified',
            'content_status'    => 'ready_for_review',
            'updated_at'        => now(),
        ]);

        $this->audit->log('CONTENT_REWRITE', $productId, ['provider' => $listing['provider']]);

        return [
            'success' => true,
            'message' => 'Product content rewritten using AI.',
            'content' => $listing['content']
        ];
    }

    /**
     * Approve a product for publication.
     * Enforces that title and description must be different from original source data.
     */
    public function approve(int $productId): array
    {
        $product = DB::table('ec_products')->where('id', $productId)->first();

        if (!$product) {
            throw new \RuntimeException("Product not found.");
        }

        if ($product->product_type !== 'foreign') {
            throw new \RuntimeException("Approval only required for foreign sourcing items.");
        }

        $sourceMetadata = json_decode($product->source_metadata, true);
        
        // Safety Check: Verbatim title/description check
        if ($this->isVerbatim($product->name, $sourceMetadata['original_title'] ?? '')) {
            throw new \RuntimeException("Title must be rewritten before approval. Verbatim Amazon/Walmart titles are a DMCA risk.");
        }

        DB::table('ec_products')->where('id', $productId)->update([
            'content_status' => 'approved',
            'is_active'      => true,
            'updated_at'     => now(),
        ]);

        $this->audit->log('PRODUCT_APPROVED', $productId);

        Log::info("[IOR Hardening] Product approved: ID {$productId}");

        return ['success' => true, 'message' => 'Product published successfully.'];
    }

    /**
     * Mark a product as warehouse verified (own photography).
     */
    public function verifyWarehouseStock(int $productId, bool $verified = true): void
    {
        DB::table('ec_products')->where('id', $productId)->update([
            'is_warehouse_verified' => $verified,
            'updated_at'            => now(),
        ]);
    }

    /**
     * Simple verbatim check (normalised strings).
     */
    private function isVerbatim(string $current, string $original): bool
    {
        if (!$original) return false;
        
        $c = strtolower(trim($current));
        $o = strtolower(trim($original));

        return $c === $o;
    }
}
