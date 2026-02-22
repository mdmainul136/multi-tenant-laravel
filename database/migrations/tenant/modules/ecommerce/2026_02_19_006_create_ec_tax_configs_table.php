<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ec_tax_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('rate', 8, 4)->comment('Percentage e.g. 15.0000 = 15%');
            $table->string('type')->default('VAT')->comment('VAT/GST/sales_tax/custom');
            $table->string('applies_to')->default('all')->comment('all/category/product');
            $table->string('category_name')->nullable()->comment('When applies_to=category');
            $table->boolean('is_inclusive')->default(false)->comment('Tax included in price or added on top');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_tax_configs');
    }
};
