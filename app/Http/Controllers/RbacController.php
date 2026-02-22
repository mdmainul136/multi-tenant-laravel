<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Services\ActivityLogService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RbacController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // ROLES
    // ══════════════════════════════════════════════════════════════

    /**
     * List all roles with their permissions.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions:id,name,group')->get();
        return response()->json(['success' => true, 'data' => $roles]);
    }

    /**
     * Create a new role.
     */
    public function createRole(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name'        => $request->input('name'),
            'guard_name'  => 'web',
            'description' => $request->input('description'),
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions'));
        }

        ActivityLogService::log('created', 'rbac', 'role', $role->id, ['name' => $role->name]);

        return response()->json(['success' => true, 'data' => $role->load('permissions')], 201);
    }

    /**
     * Update a role and its permissions.
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['success' => false, 'message' => 'System roles cannot be modified.'], 403);
        }

        $request->validate([
            'name'        => "sometimes|string|max:100|unique:roles,name,{$id}",
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update($request->only('name', 'description'));

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions'));
        }

        ActivityLogService::log('updated', 'rbac', 'role', $role->id, ['name' => $role->name]);

        return response()->json(['success' => true, 'data' => $role->fresh()->load('permissions')]);
    }

    /**
     * Delete a role.
     */
    public function deleteRole(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['success' => false, 'message' => 'System roles cannot be deleted.'], 403);
        }

        $role->delete();
        ActivityLogService::log('deleted', 'rbac', 'role', $id);

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    // ══════════════════════════════════════════════════════════════
    // PERMISSIONS
    // ══════════════════════════════════════════════════════════════

    /**
     * List all permissions grouped by module.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');
        return response()->json(['success' => true, 'data' => $permissions]);
    }

    // ══════════════════════════════════════════════════════════════
    // USER ROLE ASSIGNMENT
    // ══════════════════════════════════════════════════════════════

    /**
     * Assign roles to a user.
     */
    public function assignUserRoles(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'roles'   => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
        ]);

        $user = \App\Models\User::findOrFail($userId);
        $user->syncRoles($request->input('roles'));

        ActivityLogService::log('assigned_roles', 'rbac', 'user', $userId, ['roles' => $request->input('roles')]);

        return response()->json(['success' => true, 'data' => $user->roles]);
    }

    /**
     * Give direct permissions to a user.
     */
    public function assignUserPermissions(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'permissions'   => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $user = \App\Models\User::findOrFail($userId);
        $user->givePermissionTo(...$request->input('permissions'));

        ActivityLogService::log('assigned_permissions', 'rbac', 'user', $userId, ['permissions' => $request->input('permissions')]);

        return response()->json(['success' => true, 'data' => $user->getAllPermissions()]);
    }

    // ══════════════════════════════════════════════════════════════
    // ACTIVITY LOG
    // ══════════════════════════════════════════════════════════════

    /**
     * List staff activity logs with filtering.
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $query = DB::table('staff_activity_logs')->orderByDesc('created_at');

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($module = $request->input('module')) {
            $query->where('module', $module);
        }
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $logs    = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'total'        => $logs->total(),
                'per_page'     => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // TWO-FACTOR AUTH
    // ══════════════════════════════════════════════════════════════

    /**
     * Generate a new 2FA secret and QR code URL.
     */
    public function setup2FA(Request $request, TwoFactorAuthService $tfa): JsonResponse
    {
        $user   = $request->user();
        $secret = $tfa->generateSecret();
        $qrUrl  = $tfa->getQrCodeUrl($user->email, $secret);

        return response()->json([
            'success' => true,
            'data'    => [
                'secret' => $secret,
                'qr_url' => $qrUrl,
            ],
        ]);
    }

    /**
     * Confirm 2FA setup with a code.
     */
    public function confirm2FA(Request $request, TwoFactorAuthService $tfa): JsonResponse
    {
        $request->validate([
            'secret' => 'required|string',
            'code'   => 'required|string|size:6',
        ]);

        $valid = $tfa->verify($request->input('secret'), $request->input('code'));

        if (!$valid) {
            return response()->json(['success' => false, 'message' => 'Invalid code. Please try again.'], 422);
        }

        $tfa->enable($request->user()->id, $request->input('secret'));

        return response()->json(['success' => true, 'message' => '2FA enabled successfully.']);
    }

    /**
     * Verify 2FA code during login.
     */
    public function verify2FA(Request $request, TwoFactorAuthService $tfa): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user   = $request->user();
        $secret = $tfa->getSecret($user->id);

        if (!$secret) {
            return response()->json(['success' => false, 'message' => '2FA not configured.'], 422);
        }

        $valid = $tfa->verify($secret, $request->input('code'));

        if (!$valid) {
            ActivityLogService::log('failed_2fa', 'auth', 'user', $user->id);
            return response()->json(['success' => false, 'message' => 'Invalid 2FA code.'], 422);
        }

        // Mark 2FA as verified in this session
        if ($request->session()) {
            $request->session()->put('2fa_verified', true);
        }

        ActivityLogService::log('verified_2fa', 'auth', 'user', $user->id);

        return response()->json(['success' => true, 'message' => '2FA verified successfully.']);
    }

    /**
     * Disable 2FA.
     */
    public function disable2FA(Request $request, TwoFactorAuthService $tfa): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user   = $request->user();
        $secret = $tfa->getSecret($user->id);
        $valid  = $tfa->verify($secret, $request->input('code'));

        if (!$valid) {
            return response()->json(['success' => false, 'message' => 'Invalid code. Cannot disable 2FA.'], 422);
        }

        $tfa->disable($user->id);

        return response()->json(['success' => true, 'message' => '2FA disabled.']);
    }
}
