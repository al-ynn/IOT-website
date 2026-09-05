<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDeviceAccessAssignmentRequest;
use App\Http\Requests\Admin\UpdateDeviceAccessAssignmentRequest;
use App\Http\Resources\Admin\DeviceAccessAssignmentResource;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use Illuminate\Http\Request;

class DeviceAccessController extends Controller
{
    public function index(Request $request, DeviceAccessService $service)
    {
        $filters = $request->validate([
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'access_level' => ['nullable', 'in:viewer,full_access'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters['organization_id'] = $request->user()->organization_id;

        return DeviceAccessAssignmentResource::collection(
            $service->list($filters)->paginate($filters['per_page'] ?? 25)->withQueryString()
        );
    }

    public function store(StoreDeviceAccessAssignmentRequest $request, DeviceAccessService $service)
    {
        $assignment = $service->create(
            $request->user(),
            $request->integer('device_id'),
            $request->integer('user_id'),
            $request->string('access_level')->toString(),
        );

        return (new DeviceAccessAssignmentResource($assignment->load(['device.organization', 'user.organization', 'assignedBy'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateDeviceAccessAssignmentRequest $request, DeviceAccessAssignment $assignment, DeviceAccessService $service)
    {
        $updated = $service->update($assignment, $request->string('access_level')->toString(), $request->user());

        return new DeviceAccessAssignmentResource($updated);
    }

    public function destroy(DeviceAccessAssignment $assignment, DeviceAccessService $service)
    {
        $service->delete($assignment, request()->user());

        return response()->noContent();
    }

    public function deviceAccess(Request $request, Device $device, DeviceAccessService $service)
    {
        abort_unless((int) $device->organization_id === (int) $request->user()->organization_id, 404);

        return DeviceAccessAssignmentResource::collection(
            $service->deviceAssignments($device)->get()
        );
    }

    public function userDeviceAccess(Request $request, User $user, DeviceAccessService $service)
    {
        abort_unless((int) $user->organization_id === (int) $request->user()->organization_id, 404);

        return DeviceAccessAssignmentResource::collection(
            $service->userAssignments($user)->get()
        );
    }

    public function deviceOptions(Request $request)
    {
        $filters = $request->validate(['organization_id' => ['nullable', 'integer', 'exists:organizations,id'], 'user_id' => ['nullable', 'integer', 'exists:users,id'], 'search' => ['nullable', 'string', 'max:255'], 'exclude_monitored' => ['nullable', 'boolean']]);

        return Device::query()->where('organization_id', $request->user()->organization_id)->with('organization')->when($filters['user_id'] ?? null, fn ($q, $id) => $q->whereDoesntHave('accessAssignments', fn ($a) => $a->where('user_id', $id)))->when($filters['exclude_monitored'] ?? false, fn ($q) => $q->whereDoesntHave('monitoringUsers', fn ($u) => $u->where('users.id', $request->user()->id)))->when($filters['search'] ?? null, fn ($q, $term) => $q->where(fn ($n) => $n->where('name', 'like', "%{$term}%")->orWhere('external_id', 'like', "%{$term}%")->orWhereHas('organization', fn ($o) => $o->where('name', 'like', "%{$term}%"))))->orderBy('name')->limit(50)->get()->map(fn (Device $device) => [
            'id' => (string) $device->id,
            'name' => $device->name,
            'organizationId' => $device->organization_id ? (string) $device->organization_id : null,
            'organizationName' => $device->organization?->name ?? '',
            'status' => $device->status,
        ]);
    }

    public function userOptions(Request $request)
    {
        $filters = $request->validate(['organization_id' => ['nullable', 'integer', 'exists:organizations,id'], 'device_id' => ['nullable', 'integer', 'exists:devices,id'], 'search' => ['nullable', 'string', 'max:255']]);

        return User::query()->with('organization')->where('organization_id', $request->user()->organization_id)->whereNull('platform_role')->where('status', 'active')->when($filters['device_id'] ?? null, fn ($q, $id) => $q->whereDoesntHave('deviceAccessAssignments', fn ($a) => $a->where('device_id', $id)))->when($filters['search'] ?? null, fn ($q, $term) => $q->where(fn ($n) => $n->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))->orderBy('name')->limit(50)->get()->map(fn (User $user) => ['id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email, 'organizationId' => (string) $user->organization_id, 'organizationName' => $user->organization?->name ?? '']);
    }
}
