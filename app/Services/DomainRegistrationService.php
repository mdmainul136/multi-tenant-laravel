<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\DomainOrder;
use App\Services\NamecheapService;
use Illuminate\Support\Facades\Log;

class DomainRegistrationService
{
    protected NamecheapService $namecheap;

    public function __construct(NamecheapService $namecheap)
    {
        $this->namecheap = $namecheap;
    }
    /**
     * Register a domain through a registrar API
     * Mock implementation for now.
     */
    public function registerDomain(string $tenantId, string $domain, int $years = 1, array $contactInfo = [])
    {
        try {
            // 1. Call Namecheap API for real registration
            $result = $this->namecheap->registerDomain($domain, $years, $contactInfo);

            // 2. Unset existing primary domains for this tenant
            TenantDomain::where('tenant_id', $tenantId)->update(['is_primary' => false]);

            // 3. Create the domain record in tenant_domains as Primary and Verified
            $tenantDomain = TenantDomain::create([
                'tenant_id' => $tenantId,
                'domain' => $domain,
                'is_verified' => true,
                'is_primary' => true,
                'status' => 'verified',
            ]);

            // 4. Update the main Tenant record's domain field (Primary cache)
            Tenant::where('tenant_id', $tenantId)->update(['domain' => $domain]);

            // 5. Mark the order as completed
            DomainOrder::where('tenant_id', $tenantId)
                ->where('domain', $domain)
                ->update([
                    'status' => 'completed',
                    'expiry_date' => now()->addYears($years),
                    'registrar_data' => json_encode([
                        'provider' => 'Namecheap',
                        'status' => 'active',
                        'domain_id' => (string) $result['DomainID'],
                        'registration_date' => now()->toDateTimeString(),
                    ])
                ]);

            return $tenantDomain;
        } catch (\Exception $e) {
            Log::error('Namecheap registration error: ' . $e->getMessage());
            throw new \Exception("Failed to register domain with Namecheap: " . $e->getMessage());
        }
    }
    /**
     * Renew an existing domain
     */
    public function renewDomain(string $domain, int $years = 1)
    {
        try {
            // Call Namecheap API
            $result = $this->namecheap->renewDomain($domain, $years);

            // Update local order data
            $expiryDate = $result['expiry_date'];

            // Find the most recent completed order for this domain
            $order = DomainOrder::where('domain', $domain)
                ->where('status', 'completed')
                ->latest()
                ->first();

            if ($order && $expiryDate) {
                $order->update([
                    'expiry_date' => $expiryDate,
                    'registration_years' => $order->registration_years + $years,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Domain renewal failed for {$domain}: " . $e->getMessage());
            throw new \Exception("Failed to renew domain: " . $e->getMessage());
        }
    }
}
