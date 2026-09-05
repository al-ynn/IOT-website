<?php

namespace App\Revisions;

use Illuminate\Validation\ValidationException;

final class DraftSnapshotPolicyRegistry
{
    private const ALLOWED_TOP_LEVEL = [
        'device' => ['metadata', 'dashboard', 'parameters'],
        'dashboard' => ['metadata', 'dashboard', 'parameters'],
        'device_template' => ['metadata', 'parameters', 'dashboard'],
        'automation' => ['metadata', 'trigger', 'conditions', 'actions', 'schedule'],
        'report' => ['metadata', 'configuration'],
        'webhook' => ['configuration'],
        'location' => ['metadata'],
        'firmware' => ['metadata'],
    ];

    private const FORBIDDEN_KEYS = [
        'secret', 'signing_secret', 'secret_prefix', 'password', 'token',
        'access_token', 'refresh_token', 'api_key', 'authorization',
        'credential', 'private_key', 'assignment', 'collaborator', 'grant',
        'lifecycle', 'reviewer', 'review_state', 'publication',
        'last_seen', 'telemetry', 'runtime', 'execution',
        'delivery', 'deployment', 'report_run', 'artifact', 'binary',
        'storage_path', 'sha256',
    ];

    public function sanitize(string $type, array $snapshot): array
    {
        $allowed = self::ALLOWED_TOP_LEVEL[$type] ?? throw ValidationException::withMessages([
            'resource_type' => ['Drafts are not supported for this resource type.'],
        ]);
        $unknown = array_diff(array_keys($snapshot), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'snapshot' => ['Draft contains unsupported resource fields.'],
            ]);
        }
        $this->assertSafe($snapshot);

        return $snapshot;
    }

    public function supports(string $type): bool
    {
        return isset(self::ALLOWED_TOP_LEVEL[$type]);
    }

    public function metadata(): array
    {
        return self::ALLOWED_TOP_LEVEL;
    }

    private function assertSafe(array $value): void
    {
        foreach ($value as $key => $nested) {
            if (is_string($key) && in_array(mb_strtolower($key), self::FORBIDDEN_KEYS, true)) {
                throw ValidationException::withMessages([
                    'snapshot' => ['Draft contains security, runtime, or governed state.'],
                ]);
            }
            if (is_array($nested)) {
                $this->assertSafe($nested);
            }
        }
    }
}
