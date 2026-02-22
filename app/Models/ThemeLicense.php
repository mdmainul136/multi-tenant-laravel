<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThemeLicense extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'theme_id',
        'purchase_price',
        'revenue_split_amount',
        'license_status',
        'purchased_at'
    ];

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }
}
