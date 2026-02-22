<?php

namespace App\Modules\Tracking\Services;

class FieldMappingService
{
    /**
     * Apply custom field mappings to the payload.
     * 
     * @param array $payload Original data
     * @param array|null $mappings ['source_field' => 'target_field']
     * @return array Transformed data
     */
    public function applyMappings(array $payload, ?array $mappings): array
    {
        if (empty($mappings)) {
            return $payload;
        }

        $transformed = $payload;

        foreach ($mappings as $source => $target) {
            if ($this->hasNestedField($payload, $source)) {
                $value = $this->getNestedField($payload, $source);
                $this->setNestedField($transformed, $target, $value);
            }
        }

        return $transformed;
    }

    private function hasNestedField(array $data, string $key): bool
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return false;
            }
            $data = $data[$segment];
        }
        return true;
    }

    private function getNestedField(array $data, string $key)
    {
        foreach (explode('.', $key) as $segment) {
            $data = $data[$segment];
        }
        return $data;
    }

    private function setNestedField(array &$data, string $key, $value): void
    {
        $segments = explode('.', $key);
        while (count($segments) > 1) {
            $segment = array_shift($segments);
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                $data[$segment] = [];
            }
            $data = &$data[$segment];
        }
        $data[array_shift($segments)] = $value;
    }
}
