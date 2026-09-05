<?php

namespace App\Services\Dashboard;

use App\Collaboration\DashboardCollaborationAuthorizer;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardMapAsset;
use App\Models\Device;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use App\Services\ResourceLifecycleService;
use App\Services\ResourceRevisionService;
use App\Services\RevisionConflictService;
use App\Services\SafeResourceSaveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DashboardService
{
    public function __construct(private DashboardWidgetRegistry $registry, private ResourceRevisionService $revisions, private RevisionConflictService $conflicts, private SafeResourceSaveService $saves, private ResourceLifecycleService $lifecycle, private DashboardCollaborationAuthorizer $authorizer, private DeviceAccessService $deviceAccess) {}

    public function defaultFor(User $user, string $scope = 'personal'): Dashboard
    {
        $q = Dashboard::query()->where('owner_user_id', $user->id)->where('scope_type', $scope);
        $d = $q->where('is_default', true)->first() ?? $q->first();
        if (! $d) {
            $d = Dashboard::create(['organization_id' => $scope === 'admin_global' ? null : $user->organization_id, 'owner_user_id' => $user->id, 'name' => $scope === 'admin_global' ? 'Global Operations' : 'My Dashboard', 'scope_type' => $scope, 'is_default' => true, 'created_by' => $user->id, 'updated_by' => $user->id, 'configuration' => null]);
        }

        return $d->load('widgets');
    }

    public function deviceFor(Device $device, User $actor): Dashboard
    {
        $d = Dashboard::query()->firstOrCreate(['device_id' => $device->id], ['organization_id' => $device->organization_id, 'owner_user_id' => null, 'name' => $device->name.' Dashboard', 'scope_type' => 'device', 'is_default' => true, 'layout_version' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id, 'configuration' => null]);
        abort_unless($d->scope_type === 'device', 409, 'The Device Dashboard relationship is invalid.');

        return $d->load(['widgets', 'device']);
    }

    public function save(User $user, Dashboard $dashboard, array $data, ?Device $contextDevice = null, ?int $expectedVersion = null, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null, bool $coordinated = false): Dashboard
    {
        if (! $coordinated && in_array($dashboard->scope_type, ['personal', 'device'], true)) {
            $type = $contextDevice ? 'device' : 'dashboard';
            $class = $contextDevice ? Device::class : Dashboard::class;
            $id = $contextDevice?->id ?? $dashboard->id;
            $savedDashboard = null;
            $saved = $this->saves->execute($user, $type, $class, $id, $baseRevisionId, $contextDevice ? fn (User $actor, Device $locked) => abort_unless($this->deviceAccess->hasFullDeviceAccess($actor, $locked), 404) : fn (User $actor, Dashboard $locked) => abort_unless($this->authorizer->canEdit($actor, $locked), 404), fn ($locked) => $this->lifecycle->assertActive($type, $locked->id, "Disabled or Archived {$type} configuration cannot be edited."), function ($locked) use ($user, $dashboard, $data, $contextDevice, $expectedVersion, $baseRevisionId, &$savedDashboard) {
                $savedDashboard = $this->save($user, $dashboard, $data, $contextDevice, $expectedVersion, $baseRevisionId, null, true);

                return $locked;
            }, $contextDevice ? fn (Device $locked, User $actor) => $this->revisions->recordDevice($locked, $actor, 'Dashboard updated') : fn (Dashboard $locked, User $actor) => $this->revisions->recordDashboard($locked, $actor, 'Dashboard updated'), $baseRevisionId !== null, $idempotencyKey, ['dashboard_id' => $dashboard->id, 'layout_version' => $expectedVersion, 'data' => $data]);
            $result = $savedDashboard ?? Dashboard::with('widgets')->findOrFail($dashboard->id);
            $result->setAttribute('save_idempotency_outcome', $saved->getAttribute('save_idempotency_outcome'));

            return $result;
        }

        return DB::transaction(function () use ($user, $dashboard, $data, $contextDevice, $expectedVersion) {
            $dashboard = Dashboard::query()->lockForUpdate()->findOrFail($dashboard->id);
            $submitted = collect($data['widgets'])->pluck('id')->filter();
            if ($submitted->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['widgets' => ['Widget instance IDs must be unique.']]);
            }if (DashboardWidget::whereIn('id', $submitted)->where('dashboard_id', '!=', $dashboard->id)->exists()) {
                abort(404);
            }
            $existing = $dashboard->widgets()->get()->keyBy(fn ($w) => (string) $w->id);
            foreach ($data['widgets'] as $candidate) {
                $old = $existing->get((string) ($candidate['id'] ?? ''));
                if ($old && isset($candidate['type']) && $old->widget_type !== $candidate['type']) {
                    throw ValidationException::withMessages(['widgets' => ['Widget type is immutable; remove and add a new widget instead.']]);
                }
            }
            if ($expectedVersion !== null && $dashboard->layout_version !== $expectedVersion) {
                abort(409, 'Dashboard was changed by another user. Reload before saving.');
            }
            $widgets = array_map(function (array $widget) use ($user, $dashboard, $contextDevice, $existing) {
                $settings = $widget['settings'] ?? [];
                $source = $settings['datasource'] ?? [];
                if (array_diff(array_keys($settings), ['title', 'datasource', 'timeRange', 'chartType', 'minimum', 'maximum', 'staticValue', 'fontSize', 'fontStyle', 'aggregation', 'rowLimit', 'step', 'imageAssetId', 'markers', 'sourceMode', 'deviceIds', 'deviceTemplateId']) || array_diff(array_keys($source), ['deviceId', 'telemetryKey', 'unit'])) {
                    throw ValidationException::withMessages(['widgets' => ['Unsupported or sensitive widget setting.']]);
                }if ($dashboard->scope_type === 'device' && isset($source['deviceId']) && (string) $source['deviceId'] !== (string) $contextDevice?->id) {
                    abort(422, 'Device Dashboard widgets cannot reference another Device.');
                }if ($dashboard->scope_type === 'device') {
                    $source['deviceId'] = $contextDevice?->id;
                }
                $configuration = array_filter(['deviceId' => $source['deviceId'] ?? null, 'telemetryKey' => $source['telemetryKey'] ?? null, 'unit' => $source['unit'] ?? null, 'timeRange' => $settings['timeRange'] ?? null, 'chartType' => $settings['chartType'] ?? null, 'minimum' => $settings['minimum'] ?? null, 'maximum' => $settings['maximum'] ?? null, 'staticValue' => $settings['staticValue'] ?? null, 'fontSize' => $settings['fontSize'] ?? null, 'fontStyle' => $settings['fontStyle'] ?? null, 'aggregation' => $settings['aggregation'] ?? null, 'rowLimit' => $settings['rowLimit'] ?? null, 'step' => $settings['step'] ?? null], fn ($v) => $v !== null && $v !== '');
                if (array_key_exists('imageAssetId', $settings)) $configuration['imageAssetId'] = $settings['imageAssetId'];
                if (array_key_exists('markers', $settings)) $configuration['markers'] = $settings['markers'];
                foreach (['sourceMode', 'deviceIds', 'deviceTemplateId'] as $fleetSetting) if (array_key_exists($fleetSetting, $settings)) $configuration[$fleetSetting] = $settings[$fleetSetting];
                if (($widget['type'] ?? null) === 'image_map' && isset($configuration['imageAssetId'])) {
                    abort_unless(DashboardMapAsset::where('id', $configuration['imageAssetId'])->where('dashboard_id', $dashboard->id)->exists(), 422, 'The selected map asset does not belong to this Dashboard.');
                }
                $validationUser = $user;
                $old = $existing->get((string) ($widget['id'] ?? ''));
                if ($dashboard->scope_type === 'personal' && $old && isset($old->configuration['deviceId']) && ! is_array($settings['datasource'] ?? null)) {
                    try {
                        $this->registry->validate($user, 'personal', ['id' => $old->id, 'type' => $old->widget_type, 'title' => $old->title, 'layout' => $old->layout, 'configuration' => $old->configuration]);
                    } catch (\Throwable) {
                        $configuration = array_merge($old->configuration, array_filter(['timeRange' => $settings['timeRange'] ?? null, 'chartType' => $settings['chartType'] ?? null, 'minimum' => $settings['minimum'] ?? null, 'maximum' => $settings['maximum'] ?? null], fn ($v) => $v !== null));
                        $validationUser = User::findOrFail($dashboard->owner_user_id);
                    }
                }

                return $this->registry->validate($validationUser, $dashboard->scope_type, ['id' => $widget['id'] ?? null, 'type' => $widget['type'] ?? null, 'title' => $settings['title'] ?? null, 'layout' => $widget['layout'] ?? null, 'configuration' => $configuration], $contextDevice);
            }, $data['widgets']);
            $dashboard->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'updated_by' => $user->id, 'layout_version' => $dashboard->layout_version + 1]);
            $ids = array_column($widgets, 'id');
            $dashboard->widgets()->whereNotIn('id', $ids)->delete();
            foreach ($widgets as $position => $widget) {
                $dashboard->widgets()->updateOrCreate(['id' => $widget['id']], ['widget_type' => $widget['type'], 'title' => $widget['title'], 'layout' => $widget['layout'], 'configuration' => $widget['configuration'], 'position' => $position]);
            }

            return $dashboard->refresh()->load('widgets');
        });
    }

    public function resource(Dashboard $dashboard, User $user, ?bool $canEdit = null, ?bool $canShare = null): array
    {
        $sourceIds = $dashboard->scope_type === 'personal'
            ? $dashboard->widgets->pluck('configuration')->flatMap(fn ($configuration) => array_merge([$configuration['deviceId'] ?? null], $configuration['deviceIds'] ?? []))->filter()->map(fn ($id) => (string) $id)->unique()->values()
            : collect();
        $authorizedSourceIds = $sourceIds->isEmpty()
            ? []
            : $this->deviceAccess->accessibleDevices($user)->whereIn('id', $sourceIds)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $widgets = $dashboard->widgets->map(function ($widget) use ($dashboard, $user, $authorizedSourceIds) {
            $c = $widget->configuration;
            $data = ['id' => $widget->id, 'type' => $widget->widget_type, 'settings' => ['title' => $widget->title, 'datasource' => isset($c['deviceId']) ? ['deviceId' => (string) $c['deviceId'], 'telemetryKey' => $c['telemetryKey'] ?? '', 'unit' => $c['unit'] ?? null] : null, 'timeRange' => $c['timeRange'] ?? null, 'chartType' => $c['chartType'] ?? null, 'minimum' => $c['minimum'] ?? null, 'maximum' => $c['maximum'] ?? null], 'layout' => $widget->layout, 'available' => true];
            foreach (['staticValue', 'fontSize', 'fontStyle', 'aggregation', 'rowLimit', 'step', 'imageAssetId', 'markers', 'sourceMode', 'deviceIds', 'deviceTemplateId'] as $optionalSetting) {
                if (array_key_exists($optionalSetting, $c)) {
                    $data['settings'][$optionalSetting] = $c[$optionalSetting];
                }
            }
            if ($dashboard->scope_type === 'personal' && isset($c['deviceId'])) {
                try {
                    $this->registry->validate($user, 'personal', ['id' => $widget->id, 'type' => $widget->widget_type, 'title' => $widget->title, 'layout' => $widget->layout, 'configuration' => $c], null, $authorizedSourceIds);
                } catch (\Throwable) {
                    $data['available'] = false;
                    $data['settings']['datasource'] = null;
                }
            }

            return $data;
        })->values()->all();
        $latest = match ($dashboard->scope_type) {
            'personal' => ResourceRevision::where(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id])->latest('revision_number')->first(),
            'device' => ResourceRevision::where(['resource_type' => 'device', 'resource_id' => $dashboard->device_id])->latest('revision_number')->first(),
            default => null,
        };

        return ['id' => (string) $dashboard->id, 'name' => $dashboard->name, 'description' => $dashboard->description, 'scope' => $dashboard->scope_type, 'deviceId' => $dashboard->device_id ? (string) $dashboard->device_id : null, 'canEdit' => $canEdit ?? true, 'canShare' => $canShare ?? ($dashboard->scope_type === 'personal' && (int) $dashboard->owner_user_id === (int) $user->id), 'isDefault' => $dashboard->is_default, 'layoutVersion' => $dashboard->layout_version, 'baseRevisionId' => $latest ? (string) $latest->id : null, 'widgets' => $widgets, 'widgetDefinitions' => $this->registry->definitions($dashboard->scope_type)];
    }
}
