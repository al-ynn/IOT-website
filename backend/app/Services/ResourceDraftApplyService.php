<?php

namespace App\Services;

use App\Collaboration\{CollaborationResourceReference,CollaborationResourceRegistry};
use App\Models\{Automation,Dashboard,Device,DeviceTemplate,FirmwareArtifact,Location,Report,ResourceDraft,User,Webhook};
use App\Services\Automation\AutomationDefinitionService;
use App\Services\Automation\AutomationDefinitionValidator;
use App\Services\Dashboard\DashboardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ResourceDraftApplyService
{
    public function __construct(
        private CollaborationResourceRegistry $resources,
        private DashboardService $dashboards,
        private AutomationDefinitionService $automations,
        private AutomationDefinitionValidator $automationValidator,
        private ReportService $reports,
        private WebhookService $webhooks,
        private LocationService $locations,
        private SafeResourceSaveService $saves,
        private ResourceLifecycleService $lifecycle,
        private ResourceRevisionService $revisions,
    ) {}

    public function apply(User $user, string $type, int|string $id): array
    {
        $resolved = $this->resources->resolve(
            $user,
            new CollaborationResourceReference($type, $id),
            'edit',
        );
        $draft = ResourceDraft::query()->where([
            'resource_type' => $type,
            'resource_id' => $id,
            'user_id' => $user->id,
        ])->firstOrFail();
        $snapshot = $draft->snapshot;
        $resource = match ($type) {
            'device' => $this->applyDevice($user, $resolved->resource, $draft),
            'dashboard' => $this->applyDashboard($user, $resolved->resource, $draft),
            'automation' => $this->applyAutomation($user, $resolved->resource, $draft),
            'report' => $this->applyReport($user, $resolved->resource, $draft),
            'webhook' => $this->webhooks->update($user, $resolved->resource, $snapshot['configuration'], $draft->base_revision_id),
            'location' => $this->locations->update($user, $resolved->resource, $snapshot['metadata'], $draft->base_revision_id),
            'device_template', 'firmware' => $this->applySimple($user, $type, $resolved->resource, $resolved->definition->authorizer, $draft),
            default => throw ValidationException::withMessages(['resource_type' => ['Draft Apply is not supported for this resource type.']]),
        };
        ResourceDraft::query()->whereKey($draft->id)->where('user_id', $user->id)->delete();

        return [
            'resourceType' => $type,
            'resourceId' => (string) $resource->getKey(),
            'draftCleared' => true,
            'latestRevisionId' => (string) \App\Models\ResourceRevision::query()
                ->where(['resource_type' => $type, 'resource_id' => $id])
                ->latest('revision_number')->value('id'),
        ];
    }

    private function applyDevice(User $user, Device $device, ResourceDraft $draft): Device
    {
        $dashboard = $this->dashboards->deviceFor($device, $user);
        $this->dashboards->save(
            $user,
            $dashboard,
            $this->dashboardData($draft->snapshot),
            $device,
            null,
            $draft->base_revision_id,
        );

        return $device->refresh();
    }

    private function applyDashboard(User $user, Dashboard $dashboard, ResourceDraft $draft): Dashboard
    {
        abort_unless($dashboard->scope_type === 'personal', 404);

        return $this->dashboards->save(
            $user,
            $dashboard,
            $this->dashboardData($draft->snapshot),
            null,
            null,
            $draft->base_revision_id,
        );
    }

    private function dashboardData(array $snapshot): array
    {
        $dashboard = $snapshot['dashboard'] ?? null;
        if (! is_array($dashboard)) {
            throw ValidationException::withMessages(['snapshot.dashboard' => ['Dashboard Draft configuration is required.']]);
        }

        return [
            'name' => $dashboard['name'] ?? data_get($snapshot, 'metadata.name'),
            'description' => $dashboard['description'] ?? data_get($snapshot, 'metadata.description'),
            'widgets' => array_map(fn (array $widget): array => [
                'id' => $widget['id'] ?? null,
                'type' => $widget['type'] ?? null,
                'settings' => [
                    'title' => $widget['title'] ?? null,
                    'datasource' => isset($widget['configuration']['deviceId']) ? [
                        'deviceId' => $widget['configuration']['deviceId'],
                        'telemetryKey' => $widget['configuration']['telemetryKey'] ?? '',
                        'unit' => $widget['configuration']['unit'] ?? null,
                    ] : null,
                    'timeRange' => $widget['configuration']['timeRange'] ?? null,
                    'chartType' => $widget['configuration']['chartType'] ?? null,
                    'minimum' => $widget['configuration']['minimum'] ?? null,
                    'maximum' => $widget['configuration']['maximum'] ?? null,
                ],
                'layout' => $widget['layout'] ?? null,
            ], $dashboard['widgets'] ?? []),
        ];
    }

    private function applyAutomation(User $user, Automation $automation, ResourceDraft $draft): Automation
    {
        $snapshot = $draft->snapshot;
        $definition = [
            'name' => data_get($snapshot, 'metadata.name'),
            'description' => data_get($snapshot, 'metadata.description'),
            'enabled' => $automation->enabled,
            'trigger' => $snapshot['trigger'],
            'conditions' => $snapshot['conditions'],
            'actions' => $snapshot['actions'],
            'schedule' => $snapshot['schedule'] ?? null,
        ];

        $definition = $this->automationValidator->validate(
            $definition,
            $automation->organization,
            false,
            $user,
            true,
        );

        return $this->automations->update(
            $automation,
            $user,
            $definition,
            $draft->base_revision_id,
            null,
            $definition,
        );
    }

    private function applyReport(User $user, Report $report, ResourceDraft $draft): Report
    {
        return $this->reports->update($user, $report, [
            'name' => data_get($draft->snapshot, 'metadata.name'),
            'description' => data_get($draft->snapshot, 'metadata.description'),
            'report_type' => data_get($draft->snapshot, 'metadata.reportType'),
            'configuration' => $draft->snapshot['configuration'],
        ], $draft->base_revision_id);
    }

    private function applySimple(User $user, string $type, Model $resource, $authorizer, ResourceDraft $draft): Model
    {
        $metadata = $draft->snapshot['metadata'] ?? [];
        $name = trim((string) ($metadata['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['snapshot.metadata.name' => ['A valid resource name is required.']]);
        }
        if (isset($metadata['description']) && (! is_string($metadata['description']) || mb_strlen($metadata['description']) > 1000)) {
            throw ValidationException::withMessages(['snapshot.metadata.description' => ['Description is invalid.']]);
        }

        return $this->saves->execute(
            $user,
            $type,
            $resource::class,
            $resource->getKey(),
            $draft->base_revision_id,
            fn (User $actor, Model $locked) => abort_unless($authorizer->canEdit($actor, $locked), 404),
            fn (Model $locked) => $this->lifecycle->assertActive($type, $locked->getKey(), 'Restore the resource before applying its Draft.'),
            function (Model $locked) use ($type, $metadata): Model {
                $fields = $type === 'device_template'
                    ? ['name', 'description', 'device_type', 'protocol']
                    : ['name', 'description'];
                $normalized = [];
                foreach ($metadata as $key => $value) {
                    $column = $key === 'deviceType' ? 'device_type' : $key;
                    if (in_array($column, $fields, true)) {
                        $normalized[$column] = $value;
                    }
                }
                $locked->update($normalized);

                return $locked;
            },
            fn (Model $locked, User $actor) => $type === 'device_template'
                ? $this->revisions->recordTemplate($locked, $actor, 'Template Draft applied')
                : $this->revisions->recordFirmware($locked, $actor, 'Firmware metadata Draft applied'),
            true,
            null,
            $metadata,
        );
    }
}
