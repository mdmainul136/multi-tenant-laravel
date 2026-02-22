<?php

namespace App\Console\Commands\Tracking;

use App\Models\Tracking\TrackingContainer;
use App\Modules\Tracking\Services\DockerOrchestratorService;
use Illuminate\Console\Command;

/**
 * Artisan command for managing sGTM containers.
 *
 * Usage:
 *   php artisan sgtm:deploy {containerId} {--domain=}
 *   php artisan sgtm:stop {containerId}
 *   php artisan sgtm:health {containerId}
 *   php artisan sgtm:update-domain {containerId} {domain}
 *   php artisan sgtm:ssl {domain}
 *   php artisan sgtm:list
 */
class SgtmContainerCommand extends Command
{
    protected $signature = 'sgtm:{action} 
                            {containerId?   : GTM Container ID (e.g. GTM-XXXXXXX)} 
                            {domain?        : Custom domain for the container}
                            {--domain=      : Custom domain override for deploy}';

    protected $description = 'Manage sGTM Docker containers (deploy, stop, health, update-domain, ssl, list)';

    public function handle(DockerOrchestratorService $orchestrator): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'deploy'        => $this->handleDeploy($orchestrator),
            'stop'          => $this->handleStop($orchestrator),
            'health'        => $this->handleHealth($orchestrator),
            'update-domain' => $this->handleUpdateDomain($orchestrator),
            'ssl'           => $this->handleSsl($orchestrator),
            'list'          => $this->handleList(),
            default         => $this->error("Unknown action: {$action}") ?? 1,
        };
    }

    private function handleDeploy(DockerOrchestratorService $orchestrator): int
    {
        $container = $this->findContainer();
        if (!$container) return 1;

        $customDomain = $this->option('domain') ?? $this->argument('domain');

        $this->info("🚀 Deploying sGTM container for {$container->container_id}...");
        $result = $orchestrator->deploy($container, $customDomain);

        $this->table(['Key', 'Value'], collect($result)->map(fn ($v, $k) => [$k, $v])->toArray());
        $this->newLine();
        $this->info("✅ Container deployed! Endpoint: {$result['endpoint']}");

        return 0;
    }

    private function handleStop(DockerOrchestratorService $orchestrator): int
    {
        $container = $this->findContainer();
        if (!$container) return 1;

        if (!$this->confirm("Are you sure you want to stop container {$container->container_id}?")) {
            return 0;
        }

        $this->info("🛑 Stopping sGTM container...");
        $orchestrator->stop($container);
        $this->info("✅ Container stopped and removed.");

        return 0;
    }

    private function handleHealth(DockerOrchestratorService $orchestrator): int
    {
        $container = $this->findContainer();
        if (!$container) return 1;

        $health = $orchestrator->healthCheck($container);
        $this->table(['Key', 'Value'], collect($health)->map(fn ($v, $k) => [$k, $v ?? '-'])->toArray());

        return 0;
    }

    private function handleUpdateDomain(DockerOrchestratorService $orchestrator): int
    {
        $container = $this->findContainer();
        if (!$container) return 1;

        $newDomain = $this->argument('domain');
        if (!$newDomain) {
            $this->error('Please provide the new domain as the second argument.');
            return 1;
        }

        $this->info("🔄 Updating domain to {$newDomain}...");
        $result = $orchestrator->updateDomain($container, $newDomain);
        $this->info("✅ Domain updated: {$result['old_domain']} → {$result['new_domain']}");

        return 0;
    }

    private function handleSsl(DockerOrchestratorService $orchestrator): int
    {
        $domain = $this->argument('containerId'); // reuse first arg as domain
        if (!$domain) {
            $this->error('Please provide the domain.');
            return 1;
        }

        $this->info("🔒 Requesting SSL for {$domain}...");
        $result = $orchestrator->requestSsl($domain);
        $this->info("✅ SSL: {$result['ssl']}");

        return 0;
    }

    private function handleList(): int
    {
        $containers = TrackingContainer::all(['container_id', 'name', 'domain', 'docker_status', 'docker_port', 'is_active']);

        if ($containers->isEmpty()) {
            $this->info('No tracking containers found.');
            return 0;
        }

        $this->table(
            ['Container ID', 'Name', 'Domain', 'Docker Status', 'Port', 'Active'],
            $containers->map(fn ($c) => [
                $c->container_id,
                $c->name,
                $c->domain ?? '-',
                $c->docker_status ?? 'pending',
                $c->docker_port ?? '-',
                $c->is_active ? '✅' : '❌',
            ])->toArray()
        );

        return 0;
    }

    private function findContainer(): ?TrackingContainer
    {
        $id = $this->argument('containerId');
        if (!$id) {
            $this->error('Please provide a Container ID (e.g. GTM-XXXXXXX).');
            return null;
        }

        $container = TrackingContainer::where('container_id', $id)->first();
        if (!$container) {
            $this->error("Container not found: {$id}");
            return null;
        }

        return $container;
    }
}
