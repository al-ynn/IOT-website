<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceCredentialResource;
use App\Models\Device;
use App\Models\DeviceCredential;
use App\Services\DeviceCredentialService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDeviceCredentialController extends Controller
{
    public function __construct(private DeviceCredentialService $service) {}

    public function index(Request $r, string $device)
    {
        $d = $this->device($r, $device);

        return DeviceCredentialResource::collection($d->credentials()->with(['device:id,name,external_id', 'creator:id,name'])->latest()->get());
    }

    public function store(Request $r, string $device)
    {
        $d = $this->device($r, $device);
        $data = $r->validate(['name' => 'required|string|max:120', 'scopes' => 'nullable|array|min:1', 'scopes.*' => ['required', 'string', 'distinct', Rule::in(DeviceCredential::SCOPES)], 'expires_at' => 'nullable|date|after:now']);
        [$credential,$token] = $this->service->create($r->user(), $d, $data);

        return response()->json(['data' => (new DeviceCredentialResource($credential))->resolve($r), 'token' => $token], 201);
    }

    public function revoke(Request $r, string $device, string $credential)
    {
        $d = $this->device($r, $device);
        $c = $d->credentials()->findOrFail($credential);

        return new DeviceCredentialResource($this->service->revoke($c));
    }

    private function device(Request $r, string $id): Device
    {
        return Device::where('organization_id', $r->user()->organization_id)->findOrFail($id);
    }
}
