<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;

class DatabaseManager
{
    /**
     * Cache for tenant database connections.
     *
     * @var array
     */
    protected static array $connectionCache = [];

    /**
     * Create a new tenant database and its isolated MySQL user.
     *
     * @param string $databaseName
     * @return void
     */
    public function createTenantDatabase(string $databaseName): void
    {
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        \Log::info("Created tenant database: {$databaseName}");

        // Create an isolated MySQL user for this tenant
        try {
            $tenant = \App\Models\Tenant::where('database_name', $databaseName)->first();
            if ($tenant) {
                $isolationService = app(TenantDatabaseIsolationService::class);
                $isolationService->createIsolatedUser($tenant);

                // Assign default "starter" plan if no plan set
                if (!$tenant->database_plan_id) {
                    $starterPlan = \App\Models\TenantDatabasePlan::where('slug', 'starter')->first();
                    if ($starterPlan) {
                        $tenant->update(['database_plan_id' => $starterPlan->id]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Could not create isolated user for {$databaseName}: " . $e->getMessage());
            // Don't fail DB creation if user creation fails
        }
    }

    /**
     * Run migrations for tenant database.
     *
     * @param string $databaseName
     * @return void
     */
    public function runTenantMigrations(string $databaseName): void
    {
        // 1. Setup the dynamic connection
        $connection = $this->getTenantConnection($databaseName);
        
        // 2. Clear caches to ensure migration system sees new connection
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        
        // 3. Switch 'tenant_dynamic' connection to this database
        $this->switchToTenantDatabaseByDbName($databaseName);

        // 4. Run migrations from the tenant directory
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--database' => 'tenant_dynamic',
            '--path'     => [
                'database/migrations/tenant',
                'database/migrations/tenant/modules/ecommerce', // Include existing modules
            ],
            '--force'    => true,
        ]);

        \Log::info("Ran migrations for tenant database: {$databaseName}");
    }

    /**
     * Helper to switch to tenant DB by its database name (for provisioning).
     */
    protected function switchToTenantDatabaseByDbName(string $databaseName): void
    {
        $tenant = \App\Models\Tenant::where('database_name', $databaseName)->first();
        if (!$tenant) return;

        $username = $tenant->db_username ?? config('tenant.database.username');
        $password = $tenant->db_password_encrypted ?? config('tenant.database.password');

        Config::set('database.connections.tenant_dynamic', [
            'driver'   => 'mysql',
            'host'     => config('tenant.database.host'),
            'port'     => config('tenant.database.port'),
            'database' => $databaseName,
            'username' => $username,
            'password' => $password,
            'charset'  => config('tenant.database.charset'),
            'collation'=> config('tenant.database.collation'),
            'prefix'   => config('tenant.database.prefix'),
            'strict'   => config('tenant.database.strict'),
            'engine'   => config('tenant.database.engine'),
        ]);

        DB::purge('tenant_dynamic');
        DB::reconnect('tenant_dynamic');
    }

    /**
     * Create admin user and roles in tenant database.
     *
     * @param string $databaseName
     * @param string $email
     * @param string $password
     * @return void
     */
    public function createAdminUser(string $databaseName, string $email, string $password): void
    {
        $connection = $this->getTenantConnection($databaseName);
        
        $hashedPassword = Hash::make($password);
        
        // 1. Create the user
        $userId = $connection->table('users')->insertGetId([
            'name' => 'Admin',
            'email' => $email,
            'password' => $hashedPassword,
            'role' => 'admin', // Legacy column
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create 'admin' role if it doesn't exist (migrations should have created it if seeded, but let's be safe)
        $roleId = $connection->table('roles')->where('name', 'admin')->value('id');
        if (!$roleId) {
            $roleId = $connection->table('roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Assign role to user
        $connection->table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => \App\Models\User::class,
            'model_id' => $userId,
        ]);

        \Log::info("Created admin user and assigned 'admin' role in tenant database: {$databaseName}");
    }

    /**
     * Get tenant-specific database connection.
     *
     * @param string $databaseName
     * @return \Illuminate\Database\Connection
     */
    public function getTenantConnection(string $databaseName)
    {
        // Check cache first
        if (isset(self::$connectionCache[$databaseName])) {
            return self::$connectionCache[$databaseName];
        }

        // Create new connection configuration
        $connectionName = 'tenant_' . $databaseName;

        // FETCH ISOLATED CREDENTIALS:
        // Try to find the tenant that owns this database to get its specific user credentials
        $tenant = \App\Models\Tenant::where('database_name', $databaseName)->first();
        
        $username = $tenant->db_username ?? config('tenant.database.username');
        $password = $tenant->db_password_encrypted ?? config('tenant.database.password');
        
        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => config('tenant.database.host'),
            'port' => config('tenant.database.port'),
            'database' => $databaseName,
            'username' => $username,
            'password' => $password,
            'charset' => config('tenant.database.charset'),
            'collation' => config('tenant.database.collation'),
            'prefix' => config('tenant.database.prefix'),
            'strict' => config('tenant.database.strict'),
            'engine' => config('tenant.database.engine'),
        ]);

        // Get and cache connection
        $connection = DB::connection($connectionName);
        self::$connectionCache[$databaseName] = $connection;

        return $connection;
    }

    /**
     * Switch to tenant database dynamically.
     *
     * @param string $tenantId
     * @return void
     */
    public function switchToTenantDatabase(string $tenantId): void
    {
        $tenant = \App\Models\Tenant::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (!$tenant) {
            throw new \Exception('Tenant not found or inactive');
        }

        $databaseName = $tenant->database_name;

        // FETCH ISOLATED CREDENTIALS:
        // Use the tenant-specific isolated MySQL user if available
        $username = $tenant->db_username ?? config('tenant.database.username');
        $password = $tenant->db_password_encrypted ?? config('tenant.database.password');
        
        // Set the tenant connection as default
        Config::set('database.connections.tenant_dynamic', [
            'driver' => 'mysql',
            'host' => config('tenant.database.host'),
            'port' => config('tenant.database.port'),
            'database' => $databaseName,
            'username' => $username,
            'password' => $password,
            'charset' => config('tenant.database.charset'),
            'collation' => config('tenant.database.collation'),
            'prefix' => config('tenant.database.prefix'),
            'strict' => config('tenant.database.strict'),
            'engine' => config('tenant.database.engine'),
        ]);

        // Purge old connection and reconnect
        DB::purge('tenant_dynamic');
        DB::reconnect('tenant_dynamic');
        
        // CRITICAL: Set as default connection for this request
        DB::setDefaultConnection('tenant_dynamic');
    }
}
