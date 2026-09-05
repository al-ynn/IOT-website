<?php

namespace App\Revisions;

use App\Models\Automation;
use App\Services\Automation\AutomationDefinitionService;

final class AutomationRevisionSnapshotBuilder
{
    public const SCHEMA_VERSION = 1;

    public function __construct(private AutomationDefinitionService $definitions) {}

    public function build(Automation $automation): array
    {
        $definition = $this->definitions->definition($automation);
        unset($definition['enabled']);
        return [
            'metadata' => ['name' => $definition['name'], 'description' => $definition['description'] ?? null],
            'trigger' => $definition['trigger'],
            'conditions' => $definition['conditions'],
            'actions' => $definition['actions'],
            'schedule' => $definition['schedule'],
        ];
    }
}
