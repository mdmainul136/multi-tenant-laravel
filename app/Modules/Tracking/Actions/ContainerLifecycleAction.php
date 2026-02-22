<?php

namespace App\Modules\Tracking\Actions;

use App\Models\Tracking\TrackingContainer;

/**
 * Manages container lifecycle metadata for the Docker Control Plane.
 * 
 * NOTE: This does NOT interact with Docker directly. It records metadata
 * that a separate DevOps pipeline / Artisan command can use to provision
 * or deprovision actual Docker containers.
 */
class ContainerLifecycleAction
{
    /**
     * Record that a new sGTM Docker container has been provisioned.
     */
    public function provision(TrackingContainer $container, string $dockerId, int $port): TrackingContainer
    {
        $container->update([
            'docker_container_id' => $dockerId,
            'docker_status'       => 'running',
            'docker_port'         => $port,
            'provisioned_at'      => now(),
        ]);

        return $container->fresh();
    }

    /**
     * Mark a container as stopped (deprovisioned).
     */
    public function deprovision(TrackingContainer $container): TrackingContainer
    {
        $container->update([
            'docker_status' => 'stopped',
        ]);

        return $container->fresh();
    }

    /**
     * Get health/status info for a container.
     */
    public function healthCheck(TrackingContainer $container): array
    {
        return [
            'container_id'  => $container->container_id,
            'docker_status' => $container->docker_status ?? 'unknown',
            'docker_port'   => $container->docker_port,
            'provisioned_at' => $container->provisioned_at,
            'is_active'     => $container->is_active,
            'uptime'        => $container->provisioned_at 
                ? now()->diffForHumans($container->provisioned_at, true)
                : null,
        ];
    }
}
