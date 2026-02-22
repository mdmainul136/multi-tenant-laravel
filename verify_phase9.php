<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use App\Http\Middleware\EnforceAdvancedQuota;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenantId = 'acme22';
$tenant = Tenant::where('tenant_id', $tenantId)->first();

echo "--- Phase 9: Advanced Quota & Rate Limiting Verification ---\n";

// Reset Cache for clean test
$prefix = config('ior_quotas.redis_prefix', 'ior_quota:');
$date = date('Y-m-d');
Cache::forget("{$prefix}{$tenantId}:scraping:{$date}");
Cache::forget("{$prefix}{$tenantId}:ai:{$date}");
RateLimiter::clear('ior_rpm:' . $tenantId);

$middleware = new EnforceAdvancedQuota();

// 1. Test FREE tier limits
echo "Test 1: Testing FREE tier limits...\n";
$tenant->subscription_tier = 'free';
$tenant->save();

// Simulate 5 scrapes (the limit for free)
Cache::put("{$prefix}{$tenantId}:scraping:{$date}", 5);

$request = Request::create('/api/ior/scraper/scrape', 'POST');
$request->attributes->set('tenant_id', $tenantId);

try {
    $middleware->handle($request, function() { return response('OK'); });
    echo "Result: FAILED (Should have been blocked at limit)\n";
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    echo "Result: SUCCESS (Blocked with message: " . $e->getMessage() . ")\n";
}

// 2. Test PRO tier upgrade
echo "\nTest 2: Upgrading to PRO tier...\n";
$tenant->subscription_tier = 'pro';
$tenant->save();

try {
    $response = $middleware->handle($request, function() { return response('OK'); });
    echo "Result: SUCCESS (Allowed at 5 scrapes on PRO tier)\n";
} catch (\Exception $e) {
    echo "Result: FAILED (" . $e->getMessage() . ")\n";
}

// 3. Test Rate Limiting (RPM)
echo "\nTest 3: Testing RPM limit (hitting 100 times)...\n";
// Free tier has 10 RPM. Let's switch back to free.
$tenant->subscription_tier = 'free';
$tenant->save();
RateLimiter::clear('ior_rpm:' . $tenantId);

$blocked = false;
for ($i = 0; $i < 15; $i++) {
    try {
        $middleware->handle($request, function() { return response('OK'); });
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        if ($e->getStatusCode() == 429) {
            echo "Result: SUCCESS (Rate limited at request #" . ($i + 1) . ")\n";
            $blocked = true;
            break;
        }
    }
}

if (!$blocked) {
    echo "Result: FAILED (Not rate limited after 15 requests)\n";
}

echo "\n--- Verification Complete ---\n";
