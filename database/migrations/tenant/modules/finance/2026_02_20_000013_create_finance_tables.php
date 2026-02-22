<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        // 1. Accounts (Chart of Accounts)
        Schema::connection($this->connection)->create('ec_finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g., 1001, 4001
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_system')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // 2. Transactions (Double Entry Bookkeeping)
        Schema::connection($this->connection)->create('ec_finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable(); // e.g., Order, Payroll, Purchase
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });

        // 3. Ledger (Entries for each transaction)
        Schema::connection($this->connection)->create('ec_finance_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('ec_finance_transactions')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('ec_finance_accounts');
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_finance_ledgers');
        Schema::connection($this->connection)->dropIfExists('ec_finance_transactions');
        Schema::connection($this->connection)->dropIfExists('ec_finance_accounts');
    }
};
