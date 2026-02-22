<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DomainService;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    protected DomainService $domainService;

    public function __construct(DomainService $domainService)
    {
        $this->domainService = $domainService;
    }

    /**
     * List all domains for the current tenant
     */
    public function index(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            $domains = $this->domainService->getTenantDomains($tenantId);

            return response()->json([
                'success' => true,
                'data' => $domains
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching domains',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a new custom domain
     */
    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|regex:/^(?!:\/\/)([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,11}?$/'
        ], [
            'domain.regex' => 'Please provide a valid domain name (e.g., shop.example.com)'
        ]);

        try {
            $tenantId = $request->attributes->get('tenant_id');
            $domain = $this->domainService->addDomain($tenantId, $request->domain);

            return response()->json([
                'success' => true,
                'message' => 'Domain added successfully. Please verify your DNS records.',
                'data' => $domain
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Verify domain DNS records
     */
    public function verify(string $id)
    {
        try {
            $result = $this->domainService->verifyDomain((int)$id);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error verifying domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set domain as primary
     */
    public function setPrimary(string $id)
    {
        try {
            $domain = $this->domainService->setPrimary($id);
            return response()->json([
                'success' => true,
                'message' => 'Domain set as primary successfully',
                'data' => $domain
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get Nameservers for a domain
     */
    public function getNameservers(string $id)
    {
        try {
            $data = $this->domainService->getNameservers((int)$id);
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get DNS Host Records
     */
    public function getDNSHosts(string $id)
    {
        try {
            $data = $this->domainService->getDNSHosts((int)$id);
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update DNS Host Records
     */
    public function updateDNSHosts(Request $request, string $id)
    {
        $request->validate([
            'hosts' => 'required|array',
            'hosts.*.name' => 'required|string',
            'hosts.*.type' => 'required|string',
            'hosts.*.address' => 'required|string',
        ]);

        try {
            $success = $this->domainService->updateDNSHosts((int)$id, $request->hosts);
            return response()->json([
                'success' => $success,
                'message' => $success ? 'DNS records updated successfully' : 'Failed to update DNS records'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Renew domain
     */
    public function renew(Request $request, string $id)
    {
        $request->validate([
            'years' => 'required|integer|min:1|max:10'
        ]);

        try {
            $result = $this->domainService->renewDomain((int)$id, $request->years);
            return response()->json([
                'success' => true,
                'message' => 'Domain renewal initiated',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Remove a domain
     */
    public function destroy(string $id)
    {
        try {
            $this->domainService->deleteDomain((int)$id);
            return response()->json([
                'success' => true,
                'message' => 'Domain removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
