<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantDatabaseStat extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'database_size_mb',
        'data_size_mb',
        'index_size_mb',
        'table_count',
        'total_rows',
        'largest_table',
        'largest_table_size_mb',
        'slow_query_count',
        'write_operation_count',
        'top_tables_by_growth',
        'recorded_at',
    ];

    protected $casts = [
        'database_size_mb' => 'decimal:2',
        'data_size_mb' => 'decimal:2',
        'index_size_mb' => 'decimal:2',
        'table_count' => 'integer',
        'total_rows' => 'integer',
        'largest_table_size_mb' => 'decimal:2',
        'slow_query_count' => 'integer',
        'write_operation_count' => 'integer',
        'top_tables_by_growth' => 'array',
        'recorded_at' => 'datetime',
    ];


    /**
     * The tenant this stat belongs to
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the database size in GB
     */
    public function getDatabaseSizeGbAttribute(): float
    {
        return round($this->database_size_mb / 1024, 3);
    }

    /**
     * Scope for recent stats
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('recorded_at', '>=', now()->subDays($days));
    }
}
