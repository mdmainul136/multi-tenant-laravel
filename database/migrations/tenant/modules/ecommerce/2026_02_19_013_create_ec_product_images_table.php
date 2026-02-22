<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ec_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ec_products')->onDelete('cascade');
            $table->string('url');
            $table->string('disk')->default('public')->comment('public/s3/cloudinary');
            $table->string('path')->nullable()->comment('Relative storage path for deletion');
            $table->string('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('file_size')->nullable()->comment('Bytes');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index(['product_id', 'is_primary']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_product_images');
    }
};
