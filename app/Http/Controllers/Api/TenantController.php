<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Register a new tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'tenantId' => 'required|string|regex:/^[a-z0-9-]+$/|unique:tenants,tenant_id|max:50',
            'tenantName' => 'required|string|max:255',
            'companyName' => 'required|string|max:255',
            'businessType' => 'required|in:sole_proprietorship,partnership,llc,corporation,startup,nonprofit,franchise,cooperative,ecommerce,retail,wholesale,fashion,grocery,electronics,dropshipping,handmade,restaurant,cafe,bakery,catering,hotel,salon,healthcare,dental,pharmacy,freelancer,consulting,agency,legal,education,coaching,online_courses,fitness,yoga,real_estate,property_management,manufacturing,construction,automotive,logistics,travel,events',
            'adminName' => 'required|string|max:255',
            'adminEmail' => 'required|email|max:255',
            'adminPassword' => 'required|string|min:8',
            'phone' => 'required|regex:/^[0-9+\-\s()]+$/|max:20',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
        ], [
            // Tenant ID messages
            'tenantId.required' => 'Tenant ID is required',
            'tenantId.regex' => 'Tenant ID can only contain lowercase letters, numbers, and hyphens',
            'tenantId.unique' => 'This tenant ID is already taken. Please choose another one',
            'tenantId.max' => 'Tenant ID cannot exceed 50 characters',
            
            // Company messages
            'companyName.required' => 'Company name is required',
            'businessType.required' => 'Business type is required',
            'businessType.in' => 'Please select a valid business type',
            
            // Admin messages
            'adminName.required' => 'Admin name is required',
            'adminEmail.required' => 'Admin email is required',
            'adminEmail.email' => 'Please provide a valid email address',
            'adminPassword.required' => 'Password is required',
            'adminPassword.min' => 'Password must be at least 8 characters long',
            
            // Contact messages
            'phone.required' => 'Phone number is required',
            'phone.regex' => 'Please provide a valid phone number',
            'address.required' => 'Address is required',
            'city.required' => 'City is required',
            'country.required' => 'Country is required',
        ]);

        try {
            $tenant = $this->tenantService->createTenant($validated);

            $baseDomain = parse_url(config('app.url'), PHP_URL_HOST);

            return response()->json([
                'success' => true,
                'message' => 'Tenant registration initiated. Your store is being set up.',
                'provisioning' => true,
                'data' => [
                    'tenantId'     => $tenant->tenant_id,
                    'domain'       => $tenant->domain,
                    'subdomain'    => $tenant->tenant_id . '.' . $baseDomain,
                    'dashboardUrl' => 'https://' . $tenant->domain . '/dashboard',
                    'loginUrl'     => 'https://' . $tenant->domain . '/login',
                    'statusUrl'    => "/api/tenants/{$tenant->tenant_id}/status",
                ],
            ], 202); // 202 Accepted
        } catch (\Exception $e) {
            \Log::error('Tenant registration error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error registering tenant',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during registration. Please try again.',
            ], 500);
        }
    }

    /**
     * Get tenant information.
     *
     * @param  string  $tenantId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $tenantId)
    {
        $tenant = $this->tenantService->getTenantByTenantId($tenantId);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->tenant_id,
                'tenant_name' => $tenant->tenant_name,
                'database_name' => $tenant->database_name,
                'status' => $tenant->status,
                'created_at' => $tenant->created_at,
            ],
        ]);
    }

    /**
     * Get the current identified tenant's information.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function current(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'No tenant identified',
                'debug' => [
                    'host' => $request->getHost(),
                    'tenant_id_header' => $request->header('X-Tenant-ID'),
                ]
            ], 400);
        }


        $tenant = $this->tenantService->getTenantByTenantId($tenantId);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id'      => $tenant->tenant_id,
                'tenant_name'    => $tenant->tenant_name,
                'company_name'   => $tenant->company_name,
                'business_type'  => $tenant->business_type,
                'database_name'  => $tenant->database_name,
                'domain'         => $tenant->domain,
                'country'        => $tenant->country,
                'status'         => $tenant->status,
                'created_at'     => $tenant->created_at,
                'platform_ip'    => env('PLATFORM_IP', '127.0.0.1'),
                'base_domain'    => parse_url(config('app.url'), PHP_URL_HOST),
            ],
        ]);
    }

    /**
     * Get all tenants.

     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $tenants = $this->tenantService->getAllTenants();

        return response()->json([
            'success' => true,
            'count' => $tenants->count(),
            'data' => $tenants->map(function ($tenant) {
                return [
                    'tenant_id' => $tenant->tenant_id,
                    'tenant_name' => $tenant->tenant_name,
                    'database_name' => $tenant->database_name,
                    'status' => $tenant->status,
                    'created_at' => $tenant->created_at,
                ];
            }),
        ]);
    }

    /**
     * Check the status of tenant provisioning.
     */
    public function checkStatus(string $tenantId)
    {
        $result = $this->tenantService->checkProvisioningStatus($tenantId);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }
}
