<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\ResourceCollaborator;
use App\Services\Admin\DeviceAccessService;
use App\Services\LocationAccessService;
use App\Services\LocationService;
use App\Services\ResourceLifecycleService;
use Illuminate\Http\Request;

final class LocationController extends Controller
{
    public function __construct(private LocationAccessService $access, private DeviceAccessService $devices, private ResourceLifecycleService $lifecycle, private LocationService $locations) {}

    public function index(Request $r)
    {
        $u = $r->user();
        abort_unless($u->organization_id, 403);
        $f = $r->validate(['search' => 'nullable|string|max:120', 'per_page' => 'nullable|integer|in:25,50,100', 'page' => 'nullable|integer|min:1']);
        $authorized = $this->devices->accessibleDevices($u)->select('id');
        $page = Location::where('organization_id', $u->organization_id)->withCount(['devices as accessible_devices_count' => fn ($q) => $q->whereIn('id', $authorized)])->when($f['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.trim($v).'%'))->orderBy('name')->orderBy('id')->paginate($f['per_page'] ?? 25);

        return response()->json($page->through(fn ($l) => $this->data($u, $l, true, false)));
    }

    public function store(Request $r)
    {
        $u = $r->user();
        abort_unless($u->organization, 403);
        $d = $r->validate(['name' => 'required|string|max:120', 'description' => 'nullable|string|max:1000', 'latitude' => 'nullable|numeric|min:-90|max:90', 'longitude' => 'nullable|numeric|min:-180|max:180', 'organization_id' => 'prohibited', 'created_by' => 'prohibited']);
        $l = $this->locations->create($u, $u->organization, $d);

        return response()->json(['data' => $this->data($u, $l, false, true)], 201);
    }

    public function show(Request $r, string $id)
    {
        $l = $this->access->findViewable($r->user(), $id);

        return ['data' => $this->data($r->user(), $l, false, true)];
    }

    public function update(Request $r, string $id)
    {
        $l = $this->access->findViewable($r->user(), $id);
        $d = $r->validate(['name' => 'sometimes|required|string|max:120', 'description' => 'sometimes|nullable|string|max:1000', 'latitude' => 'sometimes|nullable|numeric|min:-90|max:90', 'longitude' => 'sometimes|nullable|numeric|min:-180|max:180', 'baseRevisionId' => 'sometimes|integer', 'organization_id' => 'prohibited', 'created_by' => 'prohibited', 'lifecycle' => 'prohibited']);
        $base = $d['baseRevisionId'] ?? null;
        unset($d['baseRevisionId']);
        $l = $this->locations->update($r->user(), $l, $d, $base, $r->header('Idempotency-Key'));

        return ['data' => $this->data($r->user(), $l, false, true)];
    }

    public function devices(Request $r, string $id)
    {
        $l = $this->access->findViewable($r->user(), $id);
        $f = $r->validate(['search' => 'nullable|string|max:120', 'per_page' => 'nullable|integer|in:25,50,100']);

        return response()->json($this->devices->accessibleDevices($r->user())->where('location_id', $l->id)->when($f['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.trim($v).'%'))->select(['id', 'name', 'external_id', 'status', 'location_id'])->orderBy('name')->paginate($f['per_page'] ?? 25));
    }

    private function data($u, Location $l, bool $count, bool $workspace): array
    {
        $state = $this->lifecycle->state('location', $l->id);
        $permission = ResourceCollaborator::where(['resource_type' => 'location', 'resource_id' => $l->id, 'user_id' => $u->id])->value('permission');

        return ['id' => (string) $l->id, 'name' => $l->name, 'description' => $workspace ? $l->description : null, 'latitude' => $l->latitude, 'longitude' => $l->longitude, 'lifecycle' => $state, 'isAssignable' => $state === 'active', 'deviceCount' => $count ? (int) $l->accessible_devices_count : null, 'access' => $u->isPlatformAdmin() ? 'admin' : $permission, 'capabilities' => ['canViewWorkspace' => $workspace, 'canUpdate' => $state === 'active' && ($u->isPlatformAdmin() || $permission === 'edit'), 'canShare' => $state === 'active' && ($u->isPlatformAdmin() || ($permission === 'edit' && (int) $l->created_by === (int) $u->id)), 'canComment' => $workspace && $state === 'active', 'canDisable' => $u->isPlatformAdmin(), 'canRestore' => $u->isPlatformAdmin()]];
    }
}
