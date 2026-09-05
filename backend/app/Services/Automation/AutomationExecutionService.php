<?php

namespace App\Services\Automation;

use App\Models\Automation;
use App\Models\AutomationAction;
use App\Models\AutomationExecution;
use App\Services\OperationalEventService;
use App\Services\ResourceLifecycleService;
use Illuminate\Support\Str;

class AutomationExecutionService
{
    public function __construct(private ConditionEvaluator $conditions, private AutomationActionExecutor $actions, private OperationalEventService $events, private AutomationRuntimeDefinitionResolver $runtime) {}

    public function execute(Automation $automation, string $triggerType, array $data = [], ?string $correlationId = null): AutomationExecution
    {
        $automation->loadMissing(['organization.users']);
        $correlationId ??= (string) Str::uuid();
        $lifecycle = app(ResourceLifecycleService::class);
        $lifecycleGeneration = $lifecycle->generation('automation', $automation->id);
        $resolved = $this->runtime->resolve($automation);
        $execution = AutomationExecution::firstOrCreate(['correlation_id' => $correlationId], ['automation_id' => $automation->id, 'automation_revision_id' => $resolved['revision']->id, 'publication_version_id' => $resolved['publicationVersion']?->id, 'organization_id' => $automation->organization_id, 'trigger_type' => $triggerType, 'trigger_data' => $this->redact($data), 'status' => 'pending']);
        if (! $execution->wasRecentlyCreated) {
            return $execution;
        }if (! $lifecycle->allowsGeneration('automation', $automation->id, $lifecycleGeneration)) {
            return $this->finish($execution, 'skipped', [], 'Automation resource is disabled or archived, or its lifecycle generation changed.');
        }if (! $automation->enabled || $automation->status !== 'active') {
            return $this->finish($execution, 'skipped', [], 'Automation is disabled.');
        }$execution->update(['status' => 'running', 'started_at' => now()]);
        try {
            $snapshot = $resolved['snapshot'];
            if (! $this->conditions->evaluate($snapshot['conditions'] ?? ['logic' => 'AND', 'conditions' => []], $data)) {
                return $this->finish($execution, 'skipped', []);
            }$results = [];
            $failed = false;
            foreach ($snapshot['actions'] ?? [] as $index => $definition) {
                if (! $lifecycle->allowsGeneration('automation', $automation->id, $lifecycleGeneration)) {
                    return $this->finish($execution, 'skipped', $results, 'Automation lifecycle changed during execution.');
                }
                $action = new AutomationAction(['automation_id' => $automation->id, 'type' => $definition['type'], 'position' => $index, 'continue_on_failure' => $definition['continueOnFailure'] ?? false, 'configuration' => array_diff_key($definition, ['type' => true, 'continueOnFailure' => true])]);
                $result = $this->actions->execute($action, $execution);
                $results[] = $result;
                if (! $result['success']) {
                    $failed = true;
                    if (! $action->continue_on_failure) {
                        break;
                    }
                }
            }$automation->update(['last_executed_at' => now()]);

            return $this->finish($execution, $failed ? 'failed' : 'completed', $results, $failed ? 'One or more actions failed.' : null);
        } catch (\Throwable $e) {
            return $this->finish($execution, 'failed', [], $e->getMessage());
        }
    }

    private function finish(AutomationExecution $e, string $status, array $results = [], ?string $error = null): AutomationExecution
    {
        $e->update(['status' => $status, 'completed_at' => now(), 'error_message' => $error, 'result_summary' => ['actions' => $results]]);
        $e = $e->refresh()->load('logs');
        if ($status === 'failed') {
            $this->events->automationFailure($e->automation, $e);
        }

        return $e;
    }

    private function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (preg_match('/token|password|secret|key/i', (string) $k)) {
                $data[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $data[$k] = $this->redact($v);
            }
        }

        return $data;
    }
}
