<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'vertical',
        'config',
        'component_blueprint',
        'capabilities',
        'preview_url',
        'is_active',
        'developer_id',
        'version',
        'submission_status',
        'price',
        'is_premium',
        'developer_metadata',
    ];

    protected $casts = [
        'config' => 'array',
        'component_blueprint' => 'array',
        'capabilities' => 'array',
        'is_active' => 'boolean',
        'is_premium' => 'boolean',
        'developer_metadata' => 'array',
    ];

    /**
     * Get the developer who submitted the theme.
     */
    public function developer()
    {
        return $this->belongsTo(\App\Models\User::class, 'developer_id');
    }
}
