<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'group', 'guard_name', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_permissions');
    }

    /**
     * Generate standard CRUD permissions for a module resource.
     * e.g., seedForModule('ecommerce', 'products') creates:
     *   ecommerce.products.view, .create, .update, .delete
     */
    public static function seedForModule(string $module, string $resource, array $actions = ['view', 'create', 'update', 'delete']): void
    {
        foreach ($actions as $action) {
            self::firstOrCreate(
                ['name' => "{$module}.{$resource}.{$action}", 'guard_name' => 'web'],
                ['group' => $module, 'description' => ucfirst($action) . ' ' . $resource]
            );
        }

        // Module-level access permission
        self::firstOrCreate(
            ['name' => "module.{$module}.access", 'guard_name' => 'web'],
            ['group' => $module, 'description' => "Access {$module} module"]
        );
    }
}
