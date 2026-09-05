<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Organization;
use App\Models\ResourceLifecycleState;
use App\Services\LocationAccessService;
use App\Services\LocationService;
use App\Services\ResourceLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminLocationController extends Controller
{
    public function __construct(private LocationAccessService $access, private LocationService $service, private ResourceLifecycleService $lifecycle) {}

    public function index(Request $r)
    {
        $f = $r->validate(['search' => 'nullable|string|max:120', 'organization_id' => 'nullable|integer|exists:organizations,id', 'lifecycle' => ['nullable', Rule::in(['active', 'disabled', 'archived'])], 'per_page' => 'nullable|integer|in:25,50,100']);
        $q = Location::where('organization_id', $r->user()->organization_id)->with(['organization:id,name', 'creator:id,name'])->withCount('devices')->when($f['search'] ?? null, fn ($x, $v) => $x->where('name', 'like', '%'.trim($v).'%'));
        if (isset($f['lifecycle'])) {
            $ids = ResourceLifecycleState::where(['resource_type' => 'location', 'state' => $f['lifecycle']])->pluck('resource_id');
            $f['lifecycle'] === 'active' ? $q->where(fn ($x) => $x->whereIn('id', $ids)->orWhereNotIn('id', ResourceLifecycleState::where('resource_type', 'location')->pluck('resource_id'))) : $q->whereIn('id', $ids);
        }

return response()->json($q->orderBy('name')->paginate($f['per_page'] ?? 25)->through(fn ($l) => $this->data($l)));
    }

    public function store(Request $r)
    {
        $d = $r->validate(['organization_id' => 'required|integer|exists:organizations,id', 'name' => 'required|string|max:120', 'description' => 'nullable|string|max:1000']);
        $org = Organization::whereKey($r->user()->organization_id)->findOrFail($d['organization_id']);

        return response()->json(['data' => $this->data($this->service->create($r->user(), $org, $d))], 201);
    }

    public function show(Request $r, string $id)
    {
        return ['data' => $this->data($this->access->findAdmin($r->user(), $id))];
    }

    public function update(Request $r, string $id)
    {
        $d = $r->validate(['name' => 'sometimes|required|string|max:120', 'description' => 'sometimes|nullable|string|max:1000', 'organization_id' => 'prohibited']);

        return ['data' => $this->data($this->service->update($r->user(), $this->access->findAdmin($r->user(), $id), $d))];
    }

    public function devices(Request $r, string $id)
    {
        $l = $this->access->findAdmin($r->user(), $id);
        $f = $r->validate(['search' => 'nullable|string|max:120', 'per_page' => 'nullable|integer|in:25,50,100']);

        return response()->json($l->devices()->when($f['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.trim($v).'%'))->select(['id', 'name', 'external_id', 'status', 'location_id'])->orderBy('name')->paginate($f['per_page'] ?? 25));
    }

    private function data(Location $l): array
    {
        $l->loadMissing(['organization:id,name', 'creator:id,name'])->loadCount('devices');

        return ['id' => (string) $l->id, 'name' => $l->name, 'description' => $l->description, 'organization' => ['id' => (string) $l->organization_id, 'name' => $l->organization->name], 'creator' => $l->creator ? ['id' => (string) $l->creator->id, 'name' => $l->creator->name] : null, 'lifecycle' => $this->lifecycle->state('location', $l->id), 'deviceCount' => $l->devices_count, 'createdAt' => $l->created_at?->toISOString(), 'meaningfulUpdatedAt' => $l->semantic_updated_at?->toISOString()];
    }
}
