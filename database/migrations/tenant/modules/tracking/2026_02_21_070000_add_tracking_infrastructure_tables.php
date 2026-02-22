<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dead Letter Queue — stores failed event sends for retry
        Schema::create('ec_tracking_dlq', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id')->index();
            $table->unsignedBigInteger('destination_id')->nullable()->index();
            $table->string('destination_type', 50)->index();      // facebook_capi, tiktok, etc.
            $table->string('event_name', 100)->index();
            $table->string('event_id', 100)->nullable();
            $table->json('event_payload');                         // Full event data
            $table->json('credentials')->nullable();               // Encrypted creds snapshot
            $table->string('error_message', 500)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('last_attempted_at')->nullable();
            $table->enum('status', ['pending', 'retrying', 'succeeded', 'failed', 'expired'])
                  ->default('pending')->index();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
        });

        // Channel Health — stores per-channel metrics
        Schema::create('ec_tracking_channel_health', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id')->index();
            $table->string('channel', 50)->index();               // facebook_capi, tiktok, etc.
            $table->date('date')->index();
            $table->unsignedBigInteger('events_sent')->default(0);
            $table->unsignedBigInteger('events_succeeded')->default(0);
            $table->unsignedBigInteger('events_failed')->default(0);
            $table->unsignedBigInteger('events_retried')->default(0);
            $table->float('avg_latency_ms')->default(0);
            $table->float('p99_latency_ms')->default(0);
            $table->json('error_breakdown')->nullable();           // { "timeout": 3, "auth": 1 }
            $table->timestamps();

            $table->unique(['container_id', 'channel', 'date']);
        });

        // Consent Records — stores per-user consent state
        Schema::create('ec_tracking_consent', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id')->index();
            $table->string('visitor_id', 150)->index();            // Cookie / external_id
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->boolean('functional')->default(true);
            $table->boolean('personalization')->default(false);
            $table->string('consent_source', 50)->nullable();      // banner, api, import
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('raw_consent')->nullable();               // Full consent payload
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['container_id', 'visitor_id']);
        });

        // Attribution touches — records touchpoints for attribution modeling
        Schema::create('ec_tracking_attribution', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id')->index();
            $table->string('visitor_id', 150)->index();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('channel', 50);                        // facebook_capi, google_ads, etc.
            $table->string('event_name', 100);
            $table->string('campaign', 255)->nullable();
            $table->string('source', 255)->nullable();             // utm_source
            $table->string('medium', 100)->nullable();             // utm_medium
            $table->string('click_id', 255)->nullable();           // gclid, fbclid, ttclid, etc.
            $table->string('click_id_type', 30)->nullable();       // gclid, fbc, ttclid, etc.
            $table->boolean('is_conversion')->default(false);
            $table->decimal('conversion_value', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('touched_at')->index();
            $table->timestamps();

            $table->index(['visitor_id', 'touched_at']);
            $table->index(['container_id', 'channel', 'touched_at']);
        });

        // Tag configurations — tag management system
        Schema::create('ec_tracking_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id')->index();
            $table->string('name', 150);
            $table->string('type', 50);                           // pixel, script, conversion, custom
            $table->string('destination_type', 50)->nullable();    // facebook_capi, google_ads, etc.
            $table->json('config');                                // Tag-specific config
            $table->json('triggers')->nullable();                  // Trigger conditions
            $table->json('variables')->nullable();                 // Variable mappings
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ec_tracking_tags');
        Schema::dropIfExists('ec_tracking_attribution');
        Schema::dropIfExists('ec_tracking_consent');
        Schema::dropIfExists('ec_tracking_channel_health');
        Schema::dropIfExists('ec_tracking_dlq');
    }
};
