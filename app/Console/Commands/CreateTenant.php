<?php

namespace App\Console\Commands;

use App\Services\TenantService;
use Illuminate\Console\Command;

class CreateTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tenant interactively';

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏢 Create New Tenant');
        $this->newLine();

        // Get tenant information
        $tenantId = $this->ask('Tenant ID (lowercase, alphanumeric and hyphens only)');
        
        if (!$this->tenantService->validateTenantId($tenantId)) {
            $this->error('❌ Invalid tenant ID format. Must contain only lowercase letters, numbers, and hyphens.');
            return Command::FAILURE;
        }

        if ($this->tenantService->tenantExists($tenantId)) {
            $this->error('❌ Tenant ID already exists. Please choose a different ID.');
            return Command::FAILURE;
        }

        $tenantName = $this->ask('Tenant Name');
        $adminEmail = $this->ask('Admin Email');
        $adminPassword = $this->secret('Admin Password (min 6 characters)');

        if (strlen($adminPassword) < 6) {
            $this->error('❌ Password must be at least 6 characters.');
            return Command::FAILURE;
        }

        // Confirm
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['Tenant ID', $tenantId],
                ['Tenant Name', $tenantName],
                ['Admin Email', $adminEmail],
                ['Database Name', \App\Models\Tenant::generateDatabaseName($tenantId)],
            ]
        );

        if (!$this->confirm('Create this tenant?', true)) {
            $this->info('Cancelled.');
            return Command::SUCCESS;
        }

        // Create tenant
        $this->info('Creating tenant...');

        try {
            $tenant = $this->tenantService->createTenant([
                'tenantId' => $tenantId,
                'tenantName' => $tenantName,
                'adminEmail' => $adminEmail,
                'adminPassword' => $adminPassword,
            ]);

            $this->newLine();
            $this->info('✅ Tenant created successfully!');
            $this->info('');
            $this->info('Tenant Details:');
            $this->info("  ID: {$tenant->tenant_id}");
            $this->info("  Name: {$tenant->tenant_name}");
            $this->info("  Database: {$tenant->database_name}");
            $this->info("  Admin Email: {$tenant->admin_email}");
            $this->newLine();
            $this->info('You can now login via the API using the admin credentials.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error creating tenant: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
