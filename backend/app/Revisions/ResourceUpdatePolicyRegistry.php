<?php

namespace App\Revisions;

use Illuminate\Validation\ValidationException;

final class ResourceUpdatePolicyRegistry
{
    public const PULL_MANAGED = 'pull_managed';
    public const IMMEDIATE = 'immediate';
    public const LIVE_ONLY = 'live_only';
    public const UNSAFE_TO_PIN = 'unsafe_to_pin';
    public const NOT_REVISIONED = 'not_revisioned';

    private const POLICIES = [
        'device' => ['mode' => self::PULL_MANAGED, 'sections' => ['dashboard']],
        'dashboard' => ['mode' => self::IMMEDIATE, 'sections' => []],
        'device_template' => ['mode' => self::IMMEDIATE, 'sections' => []],
        'automation' => ['mode' => self::IMMEDIATE, 'sections' => []],
        'report' => ['mode' => self::IMMEDIATE, 'sections' => []],
        'webhook' => ['mode' => self::IMMEDIATE, 'sections' => []],
        'location' => ['mode' => self::UNSAFE_TO_PIN, 'sections' => []],
        'firmware' => ['mode' => self::LIVE_ONLY, 'sections' => []],
    ];

    /** @return array{mode:string,sections:array<int,string>} */
    public function get(string $type): array
    {
        return self::POLICIES[$type] ?? throw ValidationException::withMessages([
            'resource_type' => ['Update policy is not defined for this resource type.'],
        ]);
    }

    public function isPullManaged(string $type): bool
    {
        return (self::POLICIES[$type]['mode'] ?? null) === self::PULL_MANAGED;
    }

    /** @return array<int,string> */
    public function pullableSections(string $type): array
    {
        return $this->isPullManaged($type) ? self::POLICIES[$type]['sections'] : [];
    }

    /** @return array<string,array{mode:string,sections:array<int,string>}> */
    public function all(): array
    {
        return self::POLICIES;
    }
}
