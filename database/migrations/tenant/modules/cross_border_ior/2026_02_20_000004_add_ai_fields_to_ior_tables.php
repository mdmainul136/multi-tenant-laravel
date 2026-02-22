<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add AI-generated content field to ior_foreign_orders (guard: idempotent)
        if (!Schema::hasColumn('ior_foreign_orders', 'ai_product_description')) {
            Schema::table('ior_foreign_orders', function (Blueprint $table) {
                $table->longText('ai_product_description')->nullable()->after('product_name')
                      ->comment('AI-generated bilingual product description (Gemini/GPT/Claude)');
            });
        }

        // Seed AI key placeholders into ior_settings
        $aiSettings = [
            ['key' => 'gemini_api_key',       'value' => '',                         'group' => 'ai'],
            ['key' => 'gemini_model',          'value' => 'gemini-1.5-flash',         'group' => 'ai'],
            ['key' => 'openai_api_key',        'value' => '',                         'group' => 'ai'],
            ['key' => 'openai_model',          'value' => 'gpt-4o-mini',              'group' => 'ai'],
            ['key' => 'claude_api_key',        'value' => '',                         'group' => 'ai'],
            ['key' => 'claude_model',          'value' => 'claude-3-5-sonnet-20241022','group' => 'ai'],
            ['key' => 'ai_preferred_provider', 'value' => 'auto',                     'group' => 'ai'],
        ];

        foreach ($aiSettings as $setting) {
            \DB::table('ior_settings')->insertOrIgnore([
                'key'        => $setting['key'],
                'value'      => $setting['value'],
                'group'      => $setting['group'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ior_foreign_orders', function (Blueprint $table) {
            $table->dropColumn('ai_product_description');
        });

        \DB::table('ior_settings')->whereIn('key', [
            'gemini_api_key', 'gemini_model',
            'openai_api_key', 'openai_model',
            'claude_api_key', 'claude_model',
            'ai_preferred_provider',
        ])->delete();
    }
};
