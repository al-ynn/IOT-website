<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeviceParameterResource;
use App\Services\Admin\DeviceAccessService;
use App\Services\DeviceParameterService;
use Illuminate\Http\Request;

class DeviceParameterController
{
    public function index(Request $r, string $device, DeviceAccessService $a, DeviceParameterService $s)
    {
        return DeviceParameterResource::collection($s->list($a->findViewableDeviceOrFail($r->user(), $device)));
    }

    public function store(Request $r, string $device, DeviceAccessService $a, DeviceParameterService $s)
    {
        $d = $a->findManageableDeviceOrFail($r->user(), $device);
        $v = $r->validate($s->createRules());

        return (new DeviceParameterResource($s->create($d, $s->allowedCreate($v), $r->user(), true, $v['baseRevisionId'] ?? null, $r->header('Idempotency-Key'))))->response()->setStatusCode(201);
    }

    public function update(Request $r, string $device, string $parameter, DeviceAccessService $a, DeviceParameterService $s)
    {
        $d = $a->findManageableDeviceOrFail($r->user(), $device);
        $v = $r->validate($s->updateRules());

        return new DeviceParameterResource($s->update($s->find($d, $parameter), $s->allowedUpdate($v), $r->user(), $v['baseRevisionId'] ?? null, $r->header('Idempotency-Key')));
    }

    public function destroy(Request $r, string $device, string $parameter, DeviceAccessService $a, DeviceParameterService $s)
    {
        $d = $a->findManageableDeviceOrFail($r->user(), $device);
        $v = $r->validate(['baseRevisionId' => ['nullable', 'integer']]);
        $s->delete($s->find($d, $parameter), $r->user(), $v['baseRevisionId'] ?? null, $r->header('Idempotency-Key'));

        return response()->noContent();
    }
}
