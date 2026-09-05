<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\DeviceParameterResource;
use App\Models\Device;
use App\Services\DeviceParameterService;
use App\Services\ResourceLifecycleService;
use Illuminate\Http\Request;

class AdminDeviceParameterController
{
    public function index(Device $device, DeviceParameterService $s)
    {
        return DeviceParameterResource::collection($s->list($device));
    }

    public function store(Request $r, Device $device, DeviceParameterService $s)
    {
        app(ResourceLifecycleService::class)->assertActive('device', $device->id, 'Restore the Device before editing Parameters.');
        $v = $r->validate($s->createRules());

        return (new DeviceParameterResource($s->create($device, $s->allowedCreate($v), $r->user(), true, $v['baseRevisionId'] ?? null, $r->header('Idempotency-Key'))))->response()->setStatusCode(201);
    }

    public function update(Request $r, Device $device, string $parameter, DeviceParameterService $s)
    {
        app(ResourceLifecycleService::class)->assertActive('device', $device->id, 'Restore the Device before editing Parameters.');
        $v = $r->validate($s->updateRules());

        return new DeviceParameterResource($s->update($s->find($device, $parameter), $s->allowedUpdate($v), $r->user(), $v['baseRevisionId'] ?? null, $r->header('Idempotency-Key')));
    }

    public function destroy(Request $r, Device $device, string $parameter, DeviceParameterService $s)
    {
        app(ResourceLifecycleService::class)->assertActive('device', $device->id, 'Restore the Device before editing Parameters.');
        $v = $r->validate(['baseRevisionId' => ['nullable', 'integer']]);
        $s->delete($s->find($device, $parameter), $r->user(), $v['baseRevisionId'] ?? null, $r->header('Idempotency-Key'));

        return response()->noContent();
    }
}
