<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ec_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('website')->nullable();
            $table->unsignedTinyInteger('rating')->default(0)->comment('1-5 star rating');
            $table->string('payment_terms')->default('net30')->comment('net15/net30/net60/prepaid/cod');
            $table->unsignedInteger('lead_time_days')->default(7);
            $table->string('currency')->default('USD');
            $table->string('status')->default('active')->comment('active/inactive/blacklisted');
            $table->text('notes')->nullable();
            $table->decimal('total_spend', 15, 2)->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_suppliers');
    }
};
