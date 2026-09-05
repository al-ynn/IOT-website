<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGlobalDeviceRequest;
use App\Http\Resources\Admin\GlobalDeviceDetailResource;
use App\Http\Resources\Admin\GlobalDeviceResource;
use App\Models\Device;
use App\Models\Organization;
use App\Services\Admin\AdminGlobalDeviceService;
use App\Services\DeviceCreationService;
use App\Services\ResourceLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminGlobalDeviceController extends Controller
{
    public function index(Request $request, AdminGlobalDeviceService $service)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'status' => ['nullable', Rule::in(['online', 'offline'])],
            'sort' => ['nullable', Rule::in(['name', 'identifier', 'status', 'last_seen', 'created_at', 'organization'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
            'recent' => ['nullable', 'boolean'],
        ]);

        $filters['organization_id'] = $request->user()->organization_id;

        return GlobalDeviceResource::collection($service->paginate($filters));
    }

    public function store(Request $request, DeviceCreationService $creation)
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'string', 'max:100'],
            'serialNumber' => ['required', 'string', 'max:255', 'unique:devices,external_id'],
            'protocol' => ['required', 'string', 'max:50'], 'location_id' => ['nullable', 'integer'], 'location' => ['prohibited'],
            'macAddress' => ['nullable', 'string', 'max:50'], 'template_id' => ['nullable', 'integer'],
            'created_by' => ['prohibited'], 'assigned_by' => ['prohibited'], 'access_level' => ['prohibited'], 'status' => ['prohibited'],
        ]);
        $organization = Organization::whereKey($request->user()->organization_id)->findOrFail($data['organization_id']);

        return (new GlobalDeviceDetailResource(app(AdminGlobalDeviceService::class)->detail($creation->create($request->user(), $organization, $data))))->response()->setStatusCode(201);
    }

    public function show(Request $request, Device $device, AdminGlobalDeviceService $service)
    {
        abort_unless((int) $device->organization_id === (int) $request->user()->organization_id, 404);

        return new GlobalDeviceDetailResource($service->detail($device));
    }

    public function update(UpdateGlobalDeviceRequest $request, Device $device, AdminGlobalDeviceService $service)
    {
        abort_unless((int) $device->organization_id === (int) $request->user()->organization_id, 404);
        app(ResourceLifecycleService::class)->assertActive('device', $device->id, 'Restore the Device before editing normal configuration.');
        $data = $request->validated();
        $base = $data['baseRevisionId'] ?? null;
        unset($data['baseRevisionId']);

        return new GlobalDeviceDetailResource($service->update($device, $data, $request->user(), $base, $request->header('Idempotency-Key')));
    }
}
