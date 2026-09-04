<?php

namespace App\Inventory;

use App\Collaboration\CollaborationResourceRegistry;
use Illuminate\Validation\ValidationException;

final class ResourceInventoryAdapterRegistry
{
    /** @var array<string, ResourceInventoryAdapter> */
    private array $adapters;

    public function __construct(CollaborationResourceRegistry $resources)
    {
        $definitions = [
            new ResourceInventoryAdapter('device', 'Device', 'devices', 'created_by', '/admin/resources/device/{id}'),
            new ResourceInventoryAdapter('device_template', 'Template', 'device_templates', 'created_by', '/admin/resources/device_template/{id}'),
            new ResourceInventoryAdapter('automation', 'Automation', 'automations', 'created_by', '/admin/resources/automation/{id}'),
            new ResourceInventoryAdapter('report', 'Report', 'reports', 'created_by', '/admin/resources/report/{id}', true),
            new ResourceInventoryAdapter('webhook', 'Webhook', 'webhooks', 'created_by', '/admin/resources/webhook/{id}', true),
            new ResourceInventoryAdapter('location', 'Location', 'locations', 'created_by', '/admin/resources/location/{id}'),
            new ResourceInventoryAdapter('firmware', 'Firmware', 'firmware_artifacts', 'uploaded_by', '/admin/resources/firmware/{id}'),
        ];

        foreach ($definitions as $definition) {
            // Inventory can only adapt a type already owned by the canonical resource registry.
            $resources->definition($definition->type);
            $this->adapters[$definition->type] = $definition;
        }
    }

    /** @return list<ResourceInventoryAdapter> */
    public function all(): array
    {
        return array_values($this->adapters);
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->adapters);
    }

    public function get(string $type): ResourceInventoryAdapter
    {
        if (! isset($this->adapters[$type])) {
            throw ValidationException::withMessages(['resource_type' => ['Unsupported inventory resource type.']]);
        }

        return $this->adapters[$type];
    }
}
