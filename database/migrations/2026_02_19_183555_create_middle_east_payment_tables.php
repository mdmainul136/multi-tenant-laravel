<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        // ── COD Orders ─────────────────────────────────────────────────────
        Schema::connection('mysql')->create('cod_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->enum('status', ['pending_payment', 'payment_collected', 'failed', 'returned', 'cancelled'])->default('pending_payment');
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->boolean('otp_required')->default(false);
            $table->string('otp_code')->nullable();        // bcrypt hashed
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('delivery_agent_id')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── COD Risk Profiles ──────────────────────────────────────────────
        Schema::connection('mysql')->create('cod_risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->decimal('return_rate', 5, 4)->default(0);   // e.g. 0.4000 = 40%
            $table->unsignedSmallInteger('cancellation_count')->default(0);
            $table->boolean('is_blacklisted')->default(false);
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->string('blacklist_reason')->nullable();
            $table->timestamps();
        });

        // ── BNPL Transactions ──────────────────────────────────────────────
        Schema::connection('mysql')->create('bnpl_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->enum('provider', ['tabby', 'tamara', 'postpay']);
            $table->string('external_id')->nullable();       // Provider's order ID
            $table->string('checkout_url')->nullable();
            $table->unsignedTinyInteger('instalments_count')->default(4);
            $table->decimal('merchant_amount', 10, 2)->nullable();   // After BNPL fee
            $table->decimal('fee_amount', 10, 2)->nullable();         // BNPL fee kept by provider
            $table->enum('status', ['pending', 'confirmed', 'refunded', 'failed'])->default('pending');
            $table->timestamps();
        });

        // ── Alter payments — add ME-specific columns ───────────────────────
        Schema::connection('mysql')->table('payments', function (Blueprint $table) {
            $table->string('gateway_driver')->nullable()->after('payment_method');   // mada,stc_pay,tabby,...
            $table->string('country', 2)->nullable()->after('gateway_driver');       // SA, AE
            $table->string('gateway_transaction_id')->nullable()->after('transaction_id');
            $table->decimal('vat_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('vat_rate', 5, 4)->default(0)->after('vat_amount');
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('vat_rate');
            $table->string('refund_method')->nullable()->after('refunded_amount');
            $table->timestamp('refunded_at')->nullable()->after('refund_method');
        });

        // ── Alter invoices — add ME compliance columns ─────────────────────
        Schema::connection('mysql')->table('invoices', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('status');
            $table->decimal('vat_rate', 5, 4)->default(0)->after('country');
            $table->decimal('vat_amount', 10, 2)->default(0)->after('vat_rate');
            $table->string('trn_number')->nullable()->after('vat_amount');       // UAE TRN
            $table->text('zatca_qr')->nullable()->after('trn_number');           // KSA ZATCA QR (Base64)
        });

        // ── Alter tenant_modules — add suspension timeline columns ─────────
        Schema::connection('mysql')->table('tenant_modules', function (Blueprint $table) {
            $table->timestamp('grace_period_ends_at')->nullable()->after('last_renewed_at');
            $table->timestamp('soft_suspended_at')->nullable()->after('grace_period_ends_at');
            $table->timestamp('suspended_at')->nullable()->after('soft_suspended_at');
            $table->timestamp('day3_reminder_sent_at')->nullable()->after('suspended_at');
            $table->timestamp('data_notice_sent_at')->nullable()->after('day3_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('bnpl_transactions');
        Schema::connection('mysql')->dropIfExists('cod_risk_profiles');
        Schema::connection('mysql')->dropIfExists('cod_orders');

        Schema::connection('mysql')->table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway_driver','country','gateway_transaction_id','vat_amount','vat_rate','refunded_amount','refund_method','refunded_at']);
        });
        Schema::connection('mysql')->table('invoices', function (Blueprint $table) {
            $table->dropColumn(['country','vat_rate','vat_amount','trn_number','zatca_qr']);
        });
        Schema::connection('mysql')->table('tenant_modules', function (Blueprint $table) {
            $table->dropColumn(['grace_period_ends_at','soft_suspended_at','suspended_at','day3_reminder_sent_at','data_notice_sent_at']);
        });
    }
};
