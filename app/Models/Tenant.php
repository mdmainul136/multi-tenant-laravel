<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'tenant_name',
        'name',
        'company_name',
        'business_type',
        'admin_name',
        'database_name',
        'email',
        'admin_email',
        'domain',
        'phone',
        'address',
        'city',
        'country',
        'status',
        'provisioning_status',
        'database_plan_id',
        'subscription_tier',
        'db_username',
        'db_password_encrypted',
        'api_key',
        'logo_url',
        'favicon_url',
        'primary_color',
        'secondary_color',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'linkedin_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'db_password_encrypted',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'db_password_encrypted' => 'encrypted',
        ];
    }

    /**
     * Scope a query to only include active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Generate database name from tenant ID.
     *
     * @param string $tenantId
     * @return string
     */
    public static function generateDatabaseName(string $tenantId): string
    {
        $prefix = config('tenant.database_prefix', 'tenant_');
        return $prefix . $tenantId;
    }

    /**
     * Get the database plan for this tenant.
     */
    public function databasePlan()
    {
        return $this->belongsTo(TenantDatabasePlan::class, 'database_plan_id');
    }

    /**
     * Get all database stats for this tenant.
     */
    public function databaseStats()
    {
        return $this->hasMany(TenantDatabaseStat::class)->orderByDesc('recorded_at');
    }

    /**
     * Get the latest database stat snapshot.
     */
    public function latestDatabaseStat()
    {
        return $this->hasOne(TenantDatabaseStat::class)->latestOfMany('recorded_at');
    }

    /**
     * Get the tenant's module subscriptions.
     */
    public function tenantModules()
    {
        return $this->hasMany(TenantModule::class);
    }
}
