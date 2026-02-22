<?php

namespace App\Console\Commands;

use App\Models\TenantDomain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GenerateTenantSSL extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:ssl {--domain= : Specific domain to issue SSL for} {--force : Force renewal even if not due}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automate Let\'s Encrypt SSL issuance for verified tenant domains using Certbot';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainName = $this->option('domain');
        
        if ($domainName) {
            $domains = TenantDomain::where('domain', $domainName)
                ->where('status', 'verified')
                ->get();
            if ($domains->isEmpty()) {
                $this->error("Verified domain '{$domainName}' not found.");
                return 1;
            }
        } else {
            // Find verified domains that haven't had SSL successfully generated yet (optional: add an ssl_status column later)
            $domains = TenantDomain::where('status', 'verified')->get();
        }

        if ($domains->isEmpty()) {
            $this->info('No verified domains found for SSL issuance.');
            return 0;
        }

        $this->info("Found {$domains->count()} domain(s). Starting SSL generation via Certbot...");

        foreach ($domains as $domain) {
            $this->line("\n-----------------------------------------");
            $this->info("Processing SSL for: {$domain->domain}");
            
            // Certbot command
            // Note: This requires Certbot to be installed on the server and the webroot to be accessible.
            // Using --webroot is usually safer for automated SaaS platforms.
            $webroot = public_path();
            $email = config('mail.from.address', 'admin@zosair.com');
            
            $command = [
                'certbot',
                'certonly',
                '--webroot',
                '-w', $webroot,
                '-d', $domain->domain,
                '--non-interactive',
                '--agree-tos',
                '--email', $email,
                '--keep-until-expiring'
            ];

            if ($this->option('force')) {
                $command[] = '--force-renewal';
            }

            $this->line("Running: " . implode(' ', $command));

            // Execute the process
            $result = Process::run($command);

            if ($result->successful()) {
                $this->info("✓ SSL successfully processed for {$domain->domain}");
                $this->line($result->output());
                
                // You could update a field here to track SSL status
                // $domain->update(['ssl_generated_at' => now()]);
                
                // Optional: Reload Nginx to apply new certificates
                $this->line("Reloading Nginx...");
                Process::run(['sudo', 'nginx', '-s', 'reload']);
            } else {
                $this->error("✗ SSL generation failed for {$domain->domain}");
                $this->error($result->errorOutput());
                Log::error("Certbot failure for {$domain->domain}: " . $result->errorOutput());
            }
        }

        $this->info("\nSSL automation task completed.");
        return 0;
    }
}
