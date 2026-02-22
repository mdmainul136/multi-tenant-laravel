<?php

namespace App\Models\HRM;

use App\Models\TenantBaseModel;

class HrSetting extends TenantBaseModel
{
    protected $table = 'ec_hr_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set($key, $value)
    {
        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
