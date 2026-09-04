<?php

namespace App\Services\Admin;

use App\Models\Device;
use App\Models\User;
use App\Services\LocationService;
use App\Services\ResourceLifecycleService;
use App\Services\ResourceRevisionService;
use App\Services\SafeResourceSaveService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AdminGlobalDeviceService
{
    public function __construct(private ResourceRevisionService $revisions, private LocationService $locations, private SafeResourceSaveService $saves, private ResourceLifecycleService $lifecycle) {}

    private const SORTS = [
        'name' => 'devices.name',
        'identifier' => 'devices.external_id',
        'status' => 'devices.status',
        'last_seen' => 'devices.last_seen',
        'created_at' => 'devices.created_at',
        'organization' => 'organizations.name',
    ];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = self::SORTS[$filters['sort'] ?? 'created_at'];
        $direction = $filters['direction'] ?? 'desc';

        return Device::query()
            ->select('devices.*')
            ->join('organizations', 'organizations.id', '=', 'devices.organization_id')
            ->with(['organization:id,name', 'creator:id,name', 'canonicalLocation:id,name'])
            ->withCount('accessAssignments')
            ->when($filters['organization_id'] ?? null, fn (Builder $query, $id) => $query->where('devices.organization_id', $id))
            ->when($filters['status'] ?? null, function (Builder $query, $status) {
                $status === 'online' ? $query->where('devices.status', 'online') : $query->where('devices.status', '!=', 'online');
            })
            ->when($filters['recent'] ?? false, fn (Builder $query) => $query->where('devices.created_at', '>=', now()->subDays(7)))
            ->when($filters['search'] ?? null, function (Builder $query, $search) {
                $term = trim((string) $search);
                if ($term === '') {
                    return;
                }
                $query->where(function (Builder $nested) use ($term) {
                    $nested->where('devices.name', 'like', "%{$term}%")
                        ->orWhere('devices.external_id', 'like', "%{$term}%")
                        ->orWhere('organizations.name', 'like', "%{$term}%");
                });
            })
            ->orderBy($sort, $direction)
            ->orderBy('devices.id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function detail(Device $device): Device
    {
        return $device->load(['organization:id,name', 'creator:id,name', 'template:id,name', 'canonicalLocation:id,name', 'accessAssignments.user:id,name,email'])
            ->loadCount('accessAssignments');
    }

    public function update(Device $device, array $data, User $actor, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): Device
    {
        $saved = $this->saves->execute($actor, 'device', Device::class, $device->id, $baseRevisionId,
            fn (User $user, Device $locked) => abort_unless($user->isPlatformAdmin() && (int) $user->organization_id === (int) $locked->organization_id, 404),
            fn (Device $locked) => $this->lifecycle->assertActive('device', $locked->id, 'Restore the Device before editing normal configuration.'),
            function (Device $locked) use ($data) {
                $location = array_key_exists('location_id', $data) ? $this->locations->assertAssignable($locked->organization, $data['location_id'] === null ? null : (int) $data['location_id']) : $locked->canonicalLocation;
                $locked->update(['name' => $data['name'] ?? $locked->name, 'type' => $data['type'] ?? $locked->type, 'protocol' => $data['protocol'] ?? $locked->protocol, 'location_id' => array_key_exists('location_id', $data) ? $location?->id : $locked->location_id, 'location' => null, 'mac_address' => array_key_exists('macAddress', $data) ? $data['macAddress'] : $locked->mac_address]);

                return $locked;
            },
            fn (Device $locked, User $user) => $this->revisions->recordDevice($locked, $user, 'Device metadata updated'),
            $baseRevisionId !== null, $idempotencyKey, $data);

        return $this->detail($saved);
    }
}
