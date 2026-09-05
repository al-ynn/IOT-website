<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\Admin\DeviceAccessService;
use App\Services\Dashboard\DashboardService;
use App\Services\ResourceLifecycleService;
use App\Services\ResourceRevisionStateService;
use Illuminate\Http\Request;

class DeviceDashboardController extends Controller
{
    public function __construct(private DeviceAccessService $access, private DashboardService $dashboards, private ResourceRevisionStateService $states) {}

    public function show(Request $request, string $device)
    {
        $model = $this->access->findViewableDeviceOrFail($request->user(), $device);
        $active = app(ResourceLifecycleService::class)->state('device', $model->id) === 'active';
        $resource = $this->resource($request, $model, $active && $this->access->canManageDevice($request->user(), $model));
        $snapshot = $this->states->acceptedSnapshot($request->user(), $model);
        if ($snapshot && isset($snapshot['dashboard'])) {
            $resource = $this->historicalDashboard($resource, $snapshot['dashboard']);
        }

        return $this->states->hasHistory('device', $model->id) ? [...$resource, 'revisionState' => $this->states->state($request->user(), 'device', $model->id)] : $resource;
    }

    public function update(Request $request, string $device)
    {
        $model = $this->access->findManageableDeviceOrFail($request->user(), $device);
        if (! $request->filled('baseRevisionId')) {
            $this->states->assertLatestForEdit($request->user(), $model);
        }

        return $this->save($request, $model);
    }

    public function adminShow(Request $request, Device $device)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);
        $resource = $this->resource($request, $device, app(ResourceLifecycleService::class)->state('device', $device->id) === 'active');

        return $this->states->hasHistory('device', $device->id) ? [...$resource, 'revisionState' => $this->states->state($request->user(), 'device', $device->id)] : $resource;
    }

    public function adminUpdate(Request $request, Device $device)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);
        app(ResourceLifecycleService::class)->assertActive('device', $device->id, 'Restore the Device before editing its Dashboard.');

        return $this->save($request, $device);
    }

    private function resource(Request $request, Device $device, bool $canEdit): array
    {
        return $this->dashboards->resource($this->dashboards->deviceFor($device, $request->user()), $request->user(), $canEdit);
    }

    private function save(Request $request, Device $device): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'layoutVersion' => ['required', 'integer', 'min:1'], 'baseRevisionId' => ['nullable', 'integer'], 'widgets' => ['present', 'array', 'max:30'], 'widgets.*' => ['array']]);
        $dashboard = $this->dashboards->deviceFor($device, $request->user());

        return $this->dashboards->resource($this->dashboards->save($request->user(), $dashboard, $data, $device, $data['layoutVersion'], $data['baseRevisionId'] ?? null, $request->header('Idempotency-Key')), $request->user(), true);
    }

    private function historicalDashboard(array $current, array $snapshot): array
    {
        $current['name'] = $snapshot['name'];
        $current['description'] = $snapshot['description'];
        $current['widgets'] = collect($snapshot['widgets'])->map(fn ($w) => ['id' => (string) $w['id'], 'type' => $w['type'], 'settings' => ['title' => $w['title'], 'datasource' => isset($w['configuration']['deviceId']) ? ['deviceId' => (string) $w['configuration']['deviceId'], 'telemetryKey' => $w['configuration']['telemetryKey'] ?? '', 'unit' => $w['configuration']['unit'] ?? null] : null, 'timeRange' => $w['configuration']['timeRange'] ?? null, 'chartType' => $w['configuration']['chartType'] ?? null, 'minimum' => $w['configuration']['minimum'] ?? null, 'maximum' => $w['configuration']['maximum'] ?? null], 'layout' => ['x' => $w['layout']['x'], 'y' => $w['layout']['y'], 'w' => $w['layout']['w'], 'h' => $w['layout']['h']], 'available' => true])->values()->all();

        return $current;
    }
}
