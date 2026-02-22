<?php

namespace App\Modules\Tracking\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tracking\TrackingContainer;
use App\Modules\Tracking\Services\PowerUpService;
use App\Modules\Tracking\Services\TrackingProxyService;
use Illuminate\Http\Request;

class ProxyController extends Controller
{
    protected TrackingProxyService $proxyService;
    protected PowerUpService $powerUpService;

    public function __construct(TrackingProxyService $proxyService, PowerUpService $powerUpService)
    {
        $this->proxyService = $proxyService;
        $this->powerUpService = $powerUpService;
    }

    /**
     * Entry point for sGTM requests.
     */
    public function handle(Request $request, $containerId)
    {
        $container = TrackingContainer::where('container_id', $containerId)
            ->where('is_active', true)
            ->firstOrFail();

        // Power-Up: Advanced Bot Filter (Pro)
        if ($this->powerUpService->isEnabled($container, 'bot_filter')) {
            $ua = $request->userAgent();
            if (preg_match('/bot|crawl|slurp|spider|mediapartners|headless|phantom|selenium/i', $ua)) {
                return response()->json(['success' => false, 'message' => 'Bot traffic ignored'], 403);
            }

            // Block known bot IPs (datacenter ranges)
            $knownBotIps = config('tracking.blocked_ips', []);
            if (in_array($request->ip(), $knownBotIps)) {
                return response()->json(['success' => false, 'message' => 'Blocked IP'], 403);
            }
        }

        // Security: API Secret Verification
        $clientSecret = $request->header('X-Stape-Secret') ?? $request->get('api_secret');
        if ($container->api_secret && $clientSecret !== $container->api_secret) {
            return response()->json(['success' => false, 'message' => 'Invalid API Secret'], 401);
        }

        $data = $request->all();
        
        $result = $this->proxyService->processEvent($container, $data);

        $response = response()->json([
            'success' => true,
            'processed_data' => $result
        ]);

        // Power-Up: Cookie Extension (Pro — server-side first-party cookie)
        if ($this->powerUpService->isEnabled($container, 'cookie_extend') && $request->has('_ext_cookie')) {
            $cookieName = $container->settings['cookie_name'] ?? 'stape_id';
            $cookieLifetime = $container->settings['cookie_lifetime'] ?? 525600; // 1 year

            $response->withCookie(cookie(
                $cookieName, 
                $request->get('_ext_cookie'), 
                $cookieLifetime,
                '/', 
                $request->getHost(), 
                true, // Secure
                true  // HttpOnly
            ));
        }

        return $response;
    }
}
