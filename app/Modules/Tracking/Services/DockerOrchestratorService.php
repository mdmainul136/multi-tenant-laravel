<?php

namespace App\Modules\Tracking\Services;

use App\Models\Tracking\TrackingContainer;
use App\Modules\Tracking\Actions\ContainerLifecycleAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

/**
 * Docker Orchestrator for sGTM Containers.
 *
 * Manages the full lifecycle of per-tenant sGTM Docker containers:
 * 1. Deploy container with unique port
 * 2. Generate NGINX reverse proxy config (domain/subdomain → container)
 * 3. Request SSL certificate (Let's Encrypt)
 * 4. Stop & remove container
 * 5. Health checks
 *
 * Domain Strategy:
 * ┌──────────────────────────────────────────────────┐
 * │  Option A: Subdomain (default, auto-generated)   │
 * │  track-{tenant}.yourdomain.com → container:8080  │
 * │                                                   │
 * │  Option B: Custom Domain (tenant provides)        │
 * │  track.clientshop.com → container:8080            │
 * └──────────────────────────────────────────────────┘
 */
class DockerOrchestratorService
{
    private string $sgtmImage;
    private string $networkName;
    private string $nginxConfigPath;
    private string $baseDomain;
    private int $portRangeStart;

    public function __construct(
        private ContainerLifecycleAction $lifecycle
    ) {
        $this->sgtmImage      = config('tracking.docker.image', 'sgtm-tracking-proxy:latest');
        $this->networkName    = config('tracking.docker.network', 'tracking_network');
        $this->nginxConfigPath = config('tracking.docker.nginx_config_path', '/etc/nginx/sites-enabled');
        $this->baseDomain     = config('tracking.docker.base_domain', 'track.yourdomain.com');
        $this->portRangeStart = config('tracking.docker.port_range_start', 9000);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  1. DEPLOY — Full provisioning pipeline
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Full deploy pipeline:
     *  1. Allocate port
     *  2. Run Docker container
     *  3. Generate NGINX config for domain/subdomain
     *  4. Reload NGINX
     *  5. (Optional) Request SSL
     */
    public function deploy(TrackingContainer $container, ?string $customDomain = null): array
    {
        $containerName = $this->makeContainerName($container);
        $port = $this->allocatePort($container);

        // Resolve the tracking domain
        $trackingDomain = $customDomain ?? $this->generateSubdomain($container);

        // Step 1: Run Docker container
        $dockerId = $this->runDockerContainer($containerName, $container, $port);

        // Step 2: Save domain to the container
        $container->update(['domain' => $trackingDomain]);

        // Step 3: Record lifecycle metadata
        $this->lifecycle->provision($container, $dockerId, $port);

        // Step 4: Generate NGINX reverse proxy config
        $this->generateNginxConfig($container, $trackingDomain, $port);

        // Step 5: Reload NGINX
        $this->reloadNginx();

        Log::info("[sGTM Orchestrator] Deployed container for {$container->container_id}", [
            'docker_id' => $dockerId,
            'port'      => $port,
            'domain'    => $trackingDomain,
        ]);

        return [
            'status'     => 'deployed',
            'docker_id'  => $dockerId,
            'port'       => $port,
            'domain'     => $trackingDomain,
            'endpoint'   => "https://{$trackingDomain}",
        ];
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  2. STOP — Teardown pipeline
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Full teardown:
     *  1. Stop & remove Docker container
     *  2. Remove NGINX config
     *  3. Reload NGINX
     *  4. Update lifecycle metadata
     */
    public function stop(TrackingContainer $container): array
    {
        $containerName = $this->makeContainerName($container);

        // Step 1: Stop Docker container
        $this->executeDockerCommand("docker stop {$containerName} && docker rm {$containerName}");

        // Step 2: Remove NGINX config
        $this->removeNginxConfig($container);

        // Step 3: Reload NGINX
        $this->reloadNginx();

        // Step 4: Update lifecycle
        $this->lifecycle->deprovision($container);

        Log::info("[sGTM Orchestrator] Stopped container for {$container->container_id}");

        return ['status' => 'stopped', 'container_id' => $container->container_id];
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  3. HEALTH — Container status check
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function healthCheck(TrackingContainer $container): array
    {
        $containerName = $this->makeContainerName($container);

        $result = $this->executeDockerCommand("docker inspect --format='{{.State.Status}}' {$containerName}");

        $dockerStatus = trim($result['output'] ?? 'unknown');

        // Sync status back to DB if different
        if ($dockerStatus !== ($container->docker_status ?? 'unknown')) {
            $container->update(['docker_status' => $dockerStatus === 'running' ? 'running' : 'error']);
        }

        return [
            'container_id'  => $container->container_id,
            'docker_status' => $dockerStatus,
            'domain'        => $container->domain,
            'port'          => $container->docker_port,
            'uptime'        => $container->provisioned_at
                ? now()->diffForHumans($container->provisioned_at, true)
                : null,
        ];
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  4. UPDATE DOMAIN — Reassign domain/subdomain
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Change the tracking domain for a container.
     * Useful when tenant wants to switch from subdomain to custom domain.
     */
    public function updateDomain(TrackingContainer $container, string $newDomain): array
    {
        $oldDomain = $container->domain;

        // Remove old NGINX config
        $this->removeNginxConfig($container);

        // Update domain
        $container->update(['domain' => $newDomain]);

        // Generate new NGINX config
        $this->generateNginxConfig($container, $newDomain, $container->docker_port);

        // Reload NGINX
        $this->reloadNginx();

        // Request SSL for new domain
        $this->requestSsl($newDomain);

        Log::info("[sGTM Orchestrator] Domain updated: {$oldDomain} → {$newDomain}");

        return [
            'old_domain' => $oldDomain,
            'new_domain' => $newDomain,
            'endpoint'   => "https://{$newDomain}",
        ];
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  5. REQUEST SSL — Let's Encrypt via Certbot
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function requestSsl(string $domain): array
    {
        $command = "certbot --nginx -d {$domain} --non-interactive --agree-tos --email admin@{$this->baseDomain}";

        $result = $this->executeDockerCommand($command);

        Log::info("[sGTM Orchestrator] SSL requested for {$domain}");

        return ['domain' => $domain, 'ssl' => $result['success'] ? 'issued' : 'failed'];
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  PRIVATE HELPERS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Generate a unique Docker container name from GTM container ID.
     */
    private function makeContainerName(TrackingContainer $container): string
    {
        return 'sgtm_' . str_replace('-', '_', strtolower($container->container_id));
    }

    /**
     * Auto-generate a subdomain for a tenant.
     * Example: track-gtmabcdef.yourdomain.com
     */
    private function generateSubdomain(TrackingContainer $container): string
    {
        $slug = strtolower(str_replace(['GTM-', 'gtm-'], '', $container->container_id));
        return "track-{$slug}.{$this->baseDomain}";
    }

    /**
     * Allocate a unique port for this container.
     */
    private function allocatePort(TrackingContainer $container): int
    {
        // Use existing port if already assigned
        if ($container->docker_port) {
            return $container->docker_port;
        }

        // Find next available port from range
        $usedPorts = TrackingContainer::whereNotNull('docker_port')
            ->pluck('docker_port')
            ->toArray();

        $port = $this->portRangeStart;
        while (in_array($port, $usedPorts)) {
            $port++;
        }

        return $port;
    }

    /**
     * Run the Docker container.
     */
    private function runDockerContainer(string $name, TrackingContainer $container, int $port): string
    {
        $powerUpsUrl = config('app.url') . '/api/tracking/proxy';

        $envVars = [
            "CONTAINER_ID"    => $container->container_id,
            "API_SECRET"      => $container->api_secret ?? '',
            "POWERUPS_URL"    => $powerUpsUrl,
            "PORT"            => $port,
            "COOKIE_NAME"     => $container->settings['cookie_name'] ?? '_stape_id',
            "CUSTOM_SCRIPT"   => $container->settings['custom_script'] ?? '',
            "LOADER_PATH"     => $this->generateLoaderPath($container),
            "CLICK_ID_RESTORE" => in_array('click_id_restore', $container->power_ups ?? []) ? 'true' : 'false',
        ];

        $envFlags = collect($envVars)
            ->map(fn ($val, $key) => "-e {$key}='{$val}'")
            ->implode(' ');

        $command = implode(' ', [
            'docker run -d',
            "--name {$name}",
            "--network {$this->networkName}",
            "--restart unless-stopped",
            "--memory 256m",
            "--cpus 0.5",
            "-p 127.0.0.1:{$port}:8080",
            $envFlags,
            $this->sgtmImage,
        ]);

        $result = $this->executeDockerCommand($command);

        return trim($result['output'] ?? $name); // Docker returns container ID
    }

    /**
     * Generate NGINX server block for a tracking domain/subdomain.
     *
     * Routes:
     *   track-{slug}.basedomain.com  →  127.0.0.1:{port}
     *   track.clientshop.com         →  127.0.0.1:{port}
     */
    private function generateNginxConfig(TrackingContainer $container, string $domain, int $port): void
    {
        $configName = $this->makeNginxConfigName($container);
        $configPath = "{$this->nginxConfigPath}/{$configName}";

        $config = <<<NGINX
# ┌──────────────────────────────────────────────┐
# │ sGTM Container: {$container->container_id}
# │ Domain: {$domain}
# │ Port: {$port}
# │ Generated: {$this->now()}
# └──────────────────────────────────────────────┘

server {
    listen 80;
    listen [::]:80;
    server_name {$domain};

    # Rate Limiting
    limit_req zone=tracking burst=50 nodelay;

    # Security Headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Health check endpoint
    location /healthz {
        access_log off;
        return 200 'ok';
    }

    # Forward all requests to sGTM container
    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";

        # Timeouts
        proxy_connect_timeout 10s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;

        # CORS for tracking scripts
        add_header Access-Control-Allow-Origin "*" always;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS" always;
        add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Stape-Secret" always;

        if (\$request_method = 'OPTIONS') {
            return 204;
        }
    }
}
NGINX;

        File::put($configPath, $config);
        Log::info("[sGTM Orchestrator] NGINX config written: {$configPath}");
    }

    /**
     * Remove NGINX config for a container.
     */
    private function removeNginxConfig(TrackingContainer $container): void
    {
        $configName = $this->makeNginxConfigName($container);
        $configPath = "{$this->nginxConfigPath}/{$configName}";

        if (File::exists($configPath)) {
            File::delete($configPath);
            Log::info("[sGTM Orchestrator] NGINX config removed: {$configPath}");
        }
    }

    /**
     * Reload NGINX to apply config changes.
     */
    private function reloadNginx(): void
    {
        $this->executeDockerCommand('nginx -t && nginx -s reload');
    }

    /**
     * Generate NGINX config filename.
     */
    private function makeNginxConfigName(TrackingContainer $container): string
    {
        return 'sgtm_' . str_replace('-', '_', strtolower($container->container_id)) . '.conf';
    }

    /**
     * Execute a shell command with error handling.
     */
    private function executeDockerCommand(string $command): array
    {
        try {
            $result = Process::run($command);
            return [
                'success' => $result->successful(),
                'output'  => $result->output(),
                'error'   => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error("[sGTM Orchestrator] Command failed: {$command}", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'output'  => '',
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get current timestamp for config comments.
     */
    private function now(): string
    {
        return now()->toDateTimeString();
    }

    /**
     * Generate an obfuscated loader path unique to the container.
     * This path is used to serve GTM/GA4 scripts without being blocked.
     */
    private function generateLoaderPath(TrackingContainer $container): string
    {
        // If container already has a loader path stored, reuse it
        if (!empty($container->settings['loader_path'])) {
            return $container->settings['loader_path'];
        }

        // Generate a random, ad-blocker-resistant path
        $segments = ['cdn', 'assets', 'lib', 'static', 'res', 'pkg'];
        $segment = $segments[array_rand($segments)];
        $hash = substr(md5($container->container_id . $container->id), 0, 6);
        $path = "/{$segment}/{$hash}.js";

        // Store it back for consistency across redeploys
        $settings = $container->settings ?? [];
        $settings['loader_path'] = $path;
        $container->update(['settings' => $settings]);

        return $path;
    }
}
