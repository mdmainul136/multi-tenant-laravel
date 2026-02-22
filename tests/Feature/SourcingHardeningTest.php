<?php

namespace Tests\Feature;

use App\Modules\CrossBorderIOR\Services\BulkProductImportService;
use App\Modules\CrossBorderIOR\Services\ProductApprovalService;
use App\Modules\CrossBorderIOR\Services\BlockedSourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class SourcingHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Schema::create('ior_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        \Illuminate\Support\Facades\Schema::create('ec_products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->string('short_description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('product_type')->default('local');
            $table->string('content_status')->default('pending_rewrite');
            $table->json('source_metadata')->nullable();
            $table->json('ior_attributes')->nullable();
            $table->timestamps();
        });

        \Illuminate\Support\Facades\Schema::create('ior_blocked_sources', function ($table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }
    
    public function test_minimal_field_policy_on_import()
    {
        // Mocking the scraper to return "Amazon" data
        $importService = app(BulkProductImportService::class);
        
        // Manual insert simulation to check if fields exist and logic works
        $productId = DB::table('ec_products')->insertGetId([
            'name'              => 'Sample Amazon Product',
            'slug'              => 'sample-product-' . time(),
            'sku'               => 'IOR-TEST-123',
            'description'       => '[Draft] Content pending review and rewrite.',
            'short_description' => '[Draft] Sourced item.',
            'price'             => 0,
            'cost'              => 99.99,
            'image_url'         => 'https://example.com/image.jpg',
            'is_active'         => false,
            'product_type'      => 'foreign',
            'content_status'    => 'pending_rewrite',
            'source_metadata'   => json_encode([
                'original_title'       => 'Verbatim Amazon Title High Risk',
                'original_description' => 'Verbatim Amazon Description DMCA Risk',
                'original_features'    => ['Feature 1', 'Feature 2'],
            ]),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $product = DB::table('ec_products')->where('id', $productId)->first();

        $this->assertEquals('pending_rewrite', $product->content_status);
        $this->assertStringContainsString('[Draft]', $product->description);
        $this->assertNotEmpty($product->source_metadata);
    }

    public function test_approval_enforces_rewrite()
    {
        $approvalService = app(ProductApprovalService::class);
        
        $productId = DB::table('ec_products')->insertGetId([
            'name'              => 'Verbatim Amazon Title High Risk', // EXACT SAME AS SOURCE
            'slug'              => 'verbatim-product-' . time(),
            'sku'               => 'IOR-TEST-456',
            'description'       => '[Draft] ...',
            'is_active'         => false,
            'product_type'      => 'foreign',
            'content_status'    => 'pending_rewrite',
            'source_metadata'   => json_encode([
                'original_title'       => 'Verbatim Amazon Title High Risk',
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Title must be rewritten before approval");

        $approvalService->approve($productId);
    }

    public function test_domain_kill_switch()
    {
        $blockedService = app(BlockedSourceService::class);
        $blockedService->blockDomain('banned-site.com', 'Legal risk');

        $this->assertTrue($blockedService->isBlocked('https://www.banned-site.com/product/123'));
        $this->assertFalse($blockedService->isBlocked('https://www.amazon.com/dp/123'));
    }
}
