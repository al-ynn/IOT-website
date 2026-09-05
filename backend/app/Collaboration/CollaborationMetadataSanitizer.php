<?php

namespace App\Collaboration;

final class CollaborationMetadataSanitizer
{
    private const SENSITIVE_KEYS = [
        'authorization', 'password', 'secret', 'credential', 'access_token',
        'refresh_token', 'api_key', 'private_key', 'cookie', 'app_key',
        'database_password',
    ];

    public function sanitize(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            $normalized = strtolower(str_replace(['-', ' '], '_', (string) $key));
            $safe[$key] = in_array($normalized, self::SENSITIVE_KEYS, true)
                ? '[REDACTED]'
                : (is_array($value) ? $this->sanitize($value) : $value);
        }
        return $safe;
    }
}
