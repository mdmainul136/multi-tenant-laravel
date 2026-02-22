<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        // Notification templates (reusable blueprints)
        Schema::connection($this->connection)->create('ec_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('e.g. order.placed, payment.received');
            $table->string('name');
            $table->string('title_template');
            $table->text('body_template')->comment('Supports {{variable}} placeholders');
            $table->string('channel')->default('in_app')->comment('in_app/email/sms/push');
            $table->string('icon')->nullable();
            $table->string('color')->nullable()->comment('Hex color for UI badge');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Actual notifications store
        Schema::connection($this->connection)->create('ec_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->comment('order/payment/stock/hr/loyalty/system');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable()->comment('Extra payload for frontend');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->string('action_url')->nullable()->comment('Deep-link for frontend navigation');
            $table->string('notifiable_type')->nullable()->comment('staff/customer/null=all-admins');
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->boolean('is_broadcast')->default(false)->comment('Sent to all admins');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('notifiable_type');
            $table->index('notifiable_id');
            $table->index('read_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_notifications');
        Schema::connection($this->connection)->dropIfExists('ec_notification_templates');
    }
};
