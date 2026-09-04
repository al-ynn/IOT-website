<?php

namespace App\Revisions;

use Illuminate\Validation\ValidationException;

final class DeviceRevisionSnapshotAdapter
{
    public const SUPPORTED_VERSIONS = [1];

    public function adapt(array $snapshot, int $version): array
    {
        if (! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            throw ValidationException::withMessages([
                'revisions' => ['Comparison unavailable for this revision pair.'],
            ]);
        }

        return [
            'metadata' => $this->only($snapshot['metadata'] ?? [], ['name', 'type', 'protocol', 'location', 'templateId']),
            'dashboard' => $this->dashboard($snapshot['dashboard'] ?? null),
            'parameters' => array_values(array_map(fn (array $parameter) => $this->only(
                $parameter,
                ['id', 'name', 'key', 'dataType', 'unit', 'description'],
            ), array_filter($snapshot['parameters'] ?? [], 'is_array'))),
        ];
    }

    private function dashboard(mixed $dashboard): ?array
    {
        if (! is_array($dashboard)) return null;

        return [
            ...$this->only($dashboard, ['id', 'name', 'description']),
            'widgets' => array_values(array_map(fn (array $widget) => [
                ...$this->only($widget, ['id', 'type', 'title']),
                'layout' => $this->only($widget['layout'] ?? [], ['x', 'y', 'w', 'h']),
                'configuration' => $this->safeConfiguration($widget['configuration'] ?? []),
            ], array_filter($dashboard['widgets'] ?? [], 'is_array'))),
        ];
    }

    private function safeConfiguration(mixed $configuration): array
    {
        if (! is_array($configuration)) return [];
        $allowed = ['metric', 'parameterId', 'deviceId', 'telemetryKey', 'timeRange', 'chartType', 'min', 'max', 'minimum', 'maximum', 'unit', 'aggregation', 'displayMode', 'decimalPlaces'];
        return $this->only($configuration, $allowed);
    }

    private function only(mixed $value, array $keys): array
    {
        if (! is_array($value)) return [];
        return array_intersect_key($value, array_flip($keys));
    }
}
