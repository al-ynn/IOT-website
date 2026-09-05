<?php

namespace App\Services\Admin;

use App\Models\CrashReport;
use App\Models\Device;
use App\Models\FirmwareDeployment;
use App\Models\OperationalEvent;
use App\Models\Organization;
use App\Models\WebhookDelivery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AdminGlobalOperationsService
{
    private const PREVIEW_LIMIT = 20;

    private const ACTIVITY_LIMIT = 10;

    private const OFFLINE_LIMIT = 10;

    private const ORGANIZATION_LIMIT = 50;

    public function overview(array $filters): array
    {
        $organizationId = $filters['organization_id'];
        $devices = $this->filteredDevices($filters);
        $total = (clone $devices)->count();
        $online = (clone $devices)->where('status', 'online')->count();

        $deviceRows = (clone $devices)
            ->with('organization:id,name')
            ->withCount('accessAssignments')
            ->orderByDesc('last_seen')
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get()
            ->map(fn (Device $device) => $this->devicePayload($device));

        $recent = (clone $devices)
            ->whereNotNull('last_seen')
            ->with('organization:id,name')
            ->orderByDesc('last_seen')
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (Device $device) => $this->activityPayload($device));

        $offline = (clone $devices)
            ->where('status', '!=', 'online')
            ->with('organization:id,name')
            ->orderByRaw('CASE WHEN last_seen IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_seen')
            ->limit(self::OFFLINE_LIMIT)
            ->get()
            ->map(fn (Device $device) => $this->activityPayload($device));

        $organizations = Organization::query()
            ->select(['organizations.id', 'organizations.name'])
            ->withCount([
                'devices as device_count',
                'devices as online_count' => fn (Builder $query) => $query->where('status', 'online'),
                'users as staff_count' => fn (Builder $query) => $query->whereNull('platform_role'),
            ])
            ->withMax('devices', 'last_seen')
            ->when($filters['organization_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
            ->orderByDesc('device_count')
            ->orderBy('name')
            ->limit(self::ORGANIZATION_LIMIT)
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'totalDevices' => $organization->device_count,
                'onlineDevices' => $organization->online_count,
                'offlineDevices' => $organization->device_count - $organization->online_count,
                'staffCount' => $organization->staff_count,
                'lastActivity' => $organization->devices_max_last_seen ? Carbon::parse($organization->devices_max_last_seen)->toISOString() : null,
            ]);

        return [
            'devices' => ['total' => $total, 'online' => $online, 'offline' => $total - $online],
            'operationalWindow' => [
                'hours' => 24,
                'errors' => OperationalEvent::query()->where('organization_id', $organizationId)->where('severity', 'error')->where('occurred_at', '>=', now('UTC')->subDay())->count(),
                'crashes' => CrashReport::query()->where('organization_id', $organizationId)->where('received_at', '>=', now('UTC')->subDay())->count(),
                'failedFirmwareDeployments' => FirmwareDeployment::query()->where('organization_id', $organizationId)->whereIn('status', ['failed', 'partial', 'delivery_unavailable'])->where('created_at', '>=', now('UTC')->subDay())->count(),
                'failedWebhookDeliveries' => WebhookDelivery::query()->whereHas('webhook', fn (Builder $query) => $query->where('organization_id', $organizationId))->where('status', 'failed')->where('updated_at', '>=', now('UTC')->subDay())->count(),
            ],
            'organizations' => [
                'total' => Organization::whereKey($organizationId)->count(),
                'withDevices' => Organization::whereKey($organizationId)->has('devices')->count(),
                'items' => $organizations,
            ],
            'filters' => [
                'organizations' => Organization::query()->whereKey($organizationId)->orderBy('name')->get(['id', 'name'])->map(fn (Organization $organization) => [
                    'id' => (string) $organization->id,
                    'name' => $organization->name,
                ]),
            ],
            'devicePreview' => $deviceRows,
            'recentDevices' => $recent,
            'offlineDevices' => $offline,
        ];
    }

    private function filteredDevices(array $filters): Builder
    {
        return Device::query()
            ->when($filters['organization_id'] ?? null, fn (Builder $query, $id) => $query->where('organization_id', $id))
            ->when($filters['status'] ?? null, function (Builder $query, $status) {
                $status === 'online' ? $query->where('status', 'online') : $query->where('status', '!=', 'online');
            })
            ->when($filters['search'] ?? null, function (Builder $query, $search) {
                $term = trim((string) $search);
                if ($term === '') {
                    return;
                }
                $query->where(function (Builder $nested) use ($term) {
                    $nested->where('name', 'like', "%{$term}%")
                        ->orWhere('external_id', 'like', "%{$term}%")
                        ->orWhereHas('organization', fn (Builder $organization) => $organization->where('name', 'like', "%{$term}%"));
                });
            });
    }

    private function devicePayload(Device $device): array
    {
        return [...$this->activityPayload($device),
            'identifier' => $device->external_id,
            'type' => $device->type,
            'assignedStaffCount' => $device->access_assignments_count,
        ];
    }

    private function activityPayload(Device $device): array
    {
        return [
            'id' => (string) $device->id,
            'name' => $device->name,
            'organization' => ['id' => (string) $device->organization_id, 'name' => $device->organization?->name ?? ''],
            'status' => $device->status === 'online' ? 'online' : 'offline',
            'lastActivity' => $device->last_seen?->toISOString(),
        ];
    }
}
