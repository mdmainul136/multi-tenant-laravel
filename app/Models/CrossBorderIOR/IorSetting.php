<?php

namespace App\Models\CrossBorderIOR;

use App\Models\TenantBaseModel;
use Illuminate\Support\Facades\Cache;

class IorSetting extends TenantBaseModel
{
    protected $table = 'ior_settings';

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * Get a setting value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("ior_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value by key.
     *
     * @param string $key
     * @param mixed $value
     * @param string $group
     * @return self
     */
    public static function set(string $key, $value, string $group = 'general'): self
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );

        Cache::forget("ior_setting_{$key}");

        return $setting;
    }

    /**
     * Get all settings in a group.
     *
     * @param string $group
     * @return \Illuminate\Support\Collection
     */
    public static function getByGroup(string $group)
    {
        return self::where('group', $group)->get()->pluck('value', 'key');
    }
}
