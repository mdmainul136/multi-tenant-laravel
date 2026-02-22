<?php

namespace App\Traits;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    /**
     * A user may have multiple roles.
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles');
    }

    /**
     * A user may have direct permissions.
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(Permission::class, 'model', 'model_has_permissions');
    }

    /**
     * Assign one or more roles to the user.
     */
    public function assignRole(string ...$roles): self
    {
        $roles = collect($roles)
            ->flatten()
            ->map(fn ($role) => Role::where('name', $role)->first())
            ->filter()
            ->all();

        $this->roles()->syncWithoutDetaching($roles);

        return $this;
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(string $role): self
    {
        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $this->roles()->detach($roleModel);
        }
        return $this;
    }

    /**
     * Sync user's roles (replaces all existing).
     */
    public function syncRoles(array $roleNames): self
    {
        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->all();
        $this->roles()->sync($roleIds);
        return $this;
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }

    /**
     * Check if the user has ANY of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) return true;
        }
        return false;
    }

    /**
     * Give a direct permission to the user.
     */
    public function givePermissionTo(string ...$permissions): self
    {
        $permissions = collect($permissions)
            ->flatten()
            ->map(fn ($permission) => Permission::where('name', $permission)->first())
            ->filter()
            ->all();

        $this->permissions()->syncWithoutDetaching($permissions);

        return $this;
    }

    /**
     * Revoke a direct permission.
     */
    public function revokePermissionTo(string $permission): self
    {
        $perm = Permission::where('name', $permission)->first();
        if ($perm) {
            $this->permissions()->detach($perm);
        }
        return $this;
    }

    /**
     * Check if the user has a specific permission (via roles or direct).
     */
    public function hasPermissionTo(string $permission): bool
    {
        // Super admin bypass
        if ($this->hasRole('super-admin') || $this->hasRole('owner')) {
            return true;
        }

        // 1. Check direct permissions
        if ($this->permissions->contains('name', $permission)) {
            return true;
        }

        // 2. Check permissions via roles
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('name', $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check module-level access (e.g., 'ecommerce', 'pos', 'crm').
     * Permission naming: "module.<module_key>.access"
     */
    public function hasModuleAccess(string $moduleKey): bool
    {
        if ($this->hasRole('super-admin') || $this->hasRole('owner')) {
            return true;
        }

        return $this->hasPermissionTo("module.{$moduleKey}.access");
    }

    /**
     * Check action-level access within a module.
     * Permission naming: "<module>.<resource>.<action>"
     * Example: "ecommerce.products.create", "pos.sales.refund"
     */
    public function can($ability, $arguments = []): bool
    {
        // If it looks like our dot-notation permission, check RBAC
        if (is_string($ability) && str_contains($ability, '.')) {
            return $this->hasPermissionTo($ability);
        }

        // Fall back to Laravel's default Gate/Policy checks
        return parent::can($ability, $arguments);
    }

    /**
     * Get all permissions (direct + via roles), flattened.
     */
    public function getAllPermissions(): Collection
    {
        $direct = $this->permissions;

        $rolePerms = $this->roles->flatMap(fn ($role) => $role->permissions);

        return $direct->merge($rolePerms)->unique('id');
    }

    /**
     * Get grouped permissions for display: ['ecommerce' => [...], 'pos' => [...]]
     */
    public function getGroupedPermissions(): Collection
    {
        return $this->getAllPermissions()->groupBy('group');
    }
}
