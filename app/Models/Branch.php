<?php

namespace App\Models;

use App\Models\TenantBaseModel;
use App\Models\POS\PosSale;
use App\Models\POS\PosSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branch extends TenantBaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'city',
        'country',
        'vat_number',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings'  => 'array',
    ];

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function sales()
    {
        return $this->hasMany(PosSale::class);
    }

    public function sessions()
    {
        return $this->hasMany(PosSession::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
