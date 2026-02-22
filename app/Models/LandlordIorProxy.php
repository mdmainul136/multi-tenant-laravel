<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordIorProxy extends Model
{
    protected $connection = 'mysql';
    protected $table = 'landlord_ior_proxies';

    protected $fillable = [
        'provider', 'proxy_type', 'host', 'port', 
        'username', 'password', 'country_code', 
        'is_active', 'fail_count', 'success_count', 
        'score', 'last_used_at', 'last_failed_at', 'meta'
    ];

    protected $casts = [
        'meta'           => 'json',
        'is_active'      => 'boolean',
        'last_used_at'   => 'datetime',
        'last_failed_at' => 'datetime',
    ];

    public function getFormattedUrlAttribute()
    {
        if ($this->username && $this->password) {
            return "http://{$this->username}:{$this->password}@{$this->host}:{$this->port}";
        }
        return "http://{$this->host}:{$this->port}";
    }
}
