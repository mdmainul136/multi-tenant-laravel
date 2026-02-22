<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TenantDomain;
use App\Services\DnsVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantDomainController extends Controller
{
    protected $dnsService;

    public function __construct(DnsVerificationService $dnsService)
    {
        $this->dnsService = $dnsService;
    }

    /**
     * List all domains for the tenant.
     */
    public function index(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        $domains = TenantDomain::where('tenant_id', $tenantId)->get();

        return response()->json([
            'success' => true,
            'data' => $domains
        ]);
    }

    /**
     * Add a new custom domain.
     */
    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|unique:tenant_domains,domain',
        ]);

        $tenantId = $request->attributes->get('tenant_id');
        
        $domain = TenantDomain::create([
            'tenant_id' => $tenantId,
            'domain' => strtolower($request->domain),
            'status' => 'pending',
            'is_verified' => false,
            'verification_token' => Str::upper(Str::random(12)),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Domain added successfully. Please configure DNS.',
            'data' => $domain
        ]);
    }

    /**
     * Verify DNS for a domain.
     */
    public function verify(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');
        $domain = TenantDomain::where('tenant_id', $tenantId)->findOrFail($id);

        $result = $this->dnsService->verify($domain->domain);

        if ($result['success']) {
            $domain->update([
                'status' => 'verified',
                'is_verified' => true,
                'verified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain verified successfully!',
                'data' => $domain
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Verification failed.',
            'debug' => $result
        ], 422);
    }

    /**
     * Set a domain as primary.
     */
    public function setPrimary(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');
        $domain = TenantDomain::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$domain->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Domain must be verified before setting as primary.'
            ], 422);
        }

        // Reset all others
        TenantDomain::where('tenant_id', $tenantId)->update(['is_primary' => false]);
        
        // Set this one
        $domain->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Primary domain updated.',
            'data' => $domain
        ]);
    }

    /**
     * Delete a domain.
     */
    public function destroy(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');
        $domain = TenantDomain::where('tenant_id', $tenantId)->findOrFail($id);

        if ($domain->is_primary) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the primary domain.'
            ], 422);
        }

        $domain->delete();

        return response()->json([
            'success' => true,
            'message' => 'Domain deleted.'
        ]);
    }
}
