<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'guard_name', 'description', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles');
    }

    /**
     * Create a role and assign permissions in one step.
     */
    public static function createWithPermissions(string $name, array $permissionNames, string $description = ''): self
    {
        $role = self::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $description]
        );

        $permIds = Permission::whereIn('name', $permissionNames)->pluck('id');
        $role->permissions()->syncWithoutDetaching($permIds);

        return $role;
    }
}
