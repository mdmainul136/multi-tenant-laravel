<?php

namespace App\Modules\Tracking\Services;

use Illuminate\Support\Facades\DB;

/**
 * Tag Management Service.
 *
 * GTM-like tag/trigger/variable management:
 *   - CRUD for tags (pixel, script, conversion, custom)
 *   - Trigger evaluation (event name, URL, custom conditions)
 *   - Variable resolution (event data, cookies, constants)
 *   - Tag firing priority and sequencing
 *   - Tag enable/disable without deletion
 */
class TagManagementService
{
    private const TABLE = 'ec_tracking_tags';

    /**
     * List all tags for a container.
     */
    public function listTags(int $containerId): array
    {
        return DB::table(self::TABLE)
            ->where('container_id', $containerId)
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn ($tag) => $this->formatTag($tag))
            ->toArray();
    }

    /**
     * Create a new tag.
     */
    public function createTag(int $containerId, array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId([
            'container_id'    => $containerId,
            'name'            => $data['name'],
            'type'            => $data['type'] ?? 'custom',
            'destination_type' => $data['destination_type'] ?? null,
            'config'          => json_encode($data['config'] ?? []),
            'triggers'        => json_encode($data['triggers'] ?? []),
            'variables'       => json_encode($data['variables'] ?? []),
            'is_active'       => $data['is_active'] ?? true,
            'priority'        => $data['priority'] ?? 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $this->getTag($id);
    }

    /**
     * Update a tag.
     */
    public function updateTag(int $tagId, array $data): array
    {
        $updates = ['updated_at' => now()];

        if (isset($data['name']))            $updates['name'] = $data['name'];
        if (isset($data['type']))            $updates['type'] = $data['type'];
        if (isset($data['destination_type'])) $updates['destination_type'] = $data['destination_type'];
        if (isset($data['config']))          $updates['config'] = json_encode($data['config']);
        if (isset($data['triggers']))        $updates['triggers'] = json_encode($data['triggers']);
        if (isset($data['variables']))       $updates['variables'] = json_encode($data['variables']);
        if (isset($data['is_active']))       $updates['is_active'] = $data['is_active'];
        if (isset($data['priority']))        $updates['priority'] = $data['priority'];

        DB::table(self::TABLE)->where('id', $tagId)->update($updates);
        return $this->getTag($tagId);
    }

    /**
     * Delete a tag.
     */
    public function deleteTag(int $tagId): bool
    {
        return DB::table(self::TABLE)->where('id', $tagId)->delete() > 0;
    }

    /**
     * Get a single tag.
     */
    public function getTag(int $tagId): array
    {
        $tag = DB::table(self::TABLE)->find($tagId);
        return $tag ? $this->formatTag($tag) : [];
    }

    /**
     * Evaluate which tags should fire for a given event.
     */
    public function evaluateTags(int $containerId, array $event): array
    {
        $tags = DB::table(self::TABLE)
            ->where('container_id', $containerId)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        $firingTags = [];

        foreach ($tags as $tag) {
            $triggers = json_decode($tag->triggers ?? '[]', true);

            if ($this->shouldFire($triggers, $event)) {
                $config = json_decode($tag->config ?? '{}', true);
                $variables = json_decode($tag->variables ?? '{}', true);

                // Resolve variables
                $resolvedConfig = $this->resolveVariables($config, $variables, $event);

                $firingTags[] = [
                    'tag_id'           => $tag->id,
                    'name'             => $tag->name,
                    'type'             => $tag->type,
                    'destination_type' => $tag->destination_type,
                    'config'           => $resolvedConfig,
                    'priority'         => $tag->priority,
                ];
            }
        }

        return $firingTags;
    }

    /**
     * Check if triggers match the event.
     */
    private function shouldFire(array $triggers, array $event): bool
    {
        if (empty($triggers)) return true; // No triggers = always fire

        foreach ($triggers as $trigger) {
            if ($this->matchTrigger($trigger, $event)) {
                return true; // OR logic between triggers
            }
        }

        return false;
    }

    /**
     * Match a single trigger against an event.
     */
    private function matchTrigger(array $trigger, array $event): bool
    {
        $conditions = $trigger['conditions'] ?? [];

        foreach ($conditions as $condition) {
            $field    = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value    = $condition['value'] ?? '';

            $eventValue = data_get($event, $field, '');

            $match = match ($operator) {
                'equals'        => (string) $eventValue === (string) $value,
                'not_equals'    => (string) $eventValue !== (string) $value,
                'contains'      => str_contains((string) $eventValue, (string) $value),
                'not_contains'  => !str_contains((string) $eventValue, (string) $value),
                'starts_with'   => str_starts_with((string) $eventValue, (string) $value),
                'ends_with'     => str_ends_with((string) $eventValue, (string) $value),
                'regex'         => (bool) preg_match("/{$value}/", (string) $eventValue),
                'in'            => in_array($eventValue, (array) $value),
                'exists'        => $eventValue !== null && $eventValue !== '',
                'not_exists'    => $eventValue === null || $eventValue === '',
                'greater_than'  => (float) $eventValue > (float) $value,
                'less_than'     => (float) $eventValue < (float) $value,
                default         => false,
            };

            if (!$match) return false; // AND logic within a trigger
        }

        return true;
    }

    /**
     * Resolve variable placeholders in tag config.
     */
    private function resolveVariables(array $config, array $variables, array $event): array
    {
        $resolved = [];

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $resolved[$key] = $this->resolveVariables($value, $variables, $event);
            } elseif (is_string($value) && preg_match_all('/\{\{(\w+)\}\}/', $value, $matches)) {
                // Replace {{variable_name}} placeholders
                foreach ($matches[1] as $varName) {
                    $varDef = $variables[$varName] ?? null;
                    $replacement = $this->resolveVariable($varDef, $varName, $event);
                    $value = str_replace("{{{$varName}}}", (string) $replacement, $value);
                }
                $resolved[$key] = $value;
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Resolve a single variable.
     */
    private function resolveVariable(?array $varDef, string $varName, array $event): mixed
    {
        if (!$varDef) {
            // Try direct event data lookup
            return data_get($event, $varName, '');
        }

        $type = $varDef['type'] ?? 'event_data';

        return match ($type) {
            'event_data' => data_get($event, $varDef['path'] ?? $varName, $varDef['default'] ?? ''),
            'constant'   => $varDef['value'] ?? '',
            'cookie'     => request()?->cookie($varDef['name'] ?? $varName) ?? $varDef['default'] ?? '',
            'random'     => rand(1000000, 9999999),
            'timestamp'  => time(),
            'url'        => request()?->fullUrl() ?? '',
            'referrer'   => request()?->header('Referer') ?? '',
            default      => $varDef['default'] ?? '',
        };
    }

    private function formatTag(object $tag): array
    {
        return [
            'id'               => $tag->id,
            'container_id'     => $tag->container_id,
            'name'             => $tag->name,
            'type'             => $tag->type,
            'destination_type' => $tag->destination_type,
            'config'           => json_decode($tag->config ?? '{}', true),
            'triggers'         => json_decode($tag->triggers ?? '[]', true),
            'variables'        => json_decode($tag->variables ?? '{}', true),
            'is_active'        => (bool) $tag->is_active,
            'priority'         => $tag->priority,
            'created_at'       => $tag->created_at,
            'updated_at'       => $tag->updated_at,
        ];
    }
}
