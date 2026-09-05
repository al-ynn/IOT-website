<?php

namespace App\Services;

final class ResourceSaveFingerprintService
{
    public function fingerprint(string $resourceType, int|string $resourceId, int|string|null $baseRevisionId, array $command): string
    {
        $canonical = [
            'operationVersion' => 1,
            'resourceType' => $resourceType,
            'resourceId' => (string) $resourceId,
            'baseRevisionId' => $baseRevisionId === null ? null : (string) $baseRevisionId,
            'command' => $this->canonicalize($command),
        ];

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) return $value;
        if (array_is_list($value)) return array_map(fn (mixed $item) => $this->canonicalize($item), $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = $this->canonicalize($item);
        return $value;
    }
}
