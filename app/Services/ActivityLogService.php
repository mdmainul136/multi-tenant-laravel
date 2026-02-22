<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * ActivityLogService
 *
 * Logs staff actions for audit purposes.
 * Stores in tenant's `staff_activity_logs` table.
 */
class ActivityLogService
{
    /**
     * Log an activity.
     *
     * @param string $action   e.g. 'created', 'updated', 'deleted', 'login', 'export'
     * @param string $module   e.g. 'ecommerce', 'pos', 'crm'
     * @param string $resource e.g. 'product', 'order', 'sale'
     * @param int|null $resourceId
     * @param array  $details  Extra metadata
     */
    public static function log(
        string $action,
        string $module,
        string $resource = '',
        ?int $resourceId = null,
        array $details = []
    ): void {
        $user = auth()->user();

        DB::table('staff_activity_logs')->insert([
            'user_id'      => $user?->id,
            'user_name'    => $user?->name ?? 'System',
            'action'       => $action,
            'module'       => $module,
            'resource'     => $resource,
            'resource_id'  => $resourceId,
            'details'      => json_encode($details),
            'ip_address'   => Request::ip(),
            'user_agent'   => Request::userAgent(),
            'created_at'   => now(),
        ]);
    }

    /**
     * Log a login event.
     */
    public static function logLogin(): void
    {
        self::log('login', 'auth', 'session');
    }

    /**
     * Log a logout event.
     */
    public static function logLogout(): void
    {
        self::log('logout', 'auth', 'session');
    }

    /**
     * Log a failed login attempt.
     */
    public static function logFailedLogin(string $email): void
    {
        DB::table('staff_activity_logs')->insert([
            'user_id'    => null,
            'user_name'  => $email,
            'action'     => 'failed_login',
            'module'     => 'auth',
            'resource'   => 'session',
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
