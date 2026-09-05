<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeviceSnapshotResource;
use App\Services\Admin\DeviceAccessService;
use App\Services\DeviceSnapshotService;
use Illuminate\Http\Request;

class DeviceSnapshotController extends Controller
{
    public function __construct(private DeviceAccessService $access, private DeviceSnapshotService $snapshots) {}

    public function index(Request $request, string $device)
    {
        $target = $this->access->findViewableDeviceOrFail($request->user(), $device);
        $filters = $request->validate(['per_page' => 'nullable|integer|in:25,50,100']);

        return DeviceSnapshotResource::collection($target->snapshots()->with('creator:id,name')->latest('captured_at')->paginate($filters['per_page'] ?? 25));
    }

    public function store(Request $request, string $device)
    {
        $target = $this->access->findManageableDeviceOrFail($request->user(), $device);
        $data = $request->validate(['name' => 'required|string|max:150', 'description' => 'nullable|string|max:1000', 'device_id' => 'prohibited', 'organization_id' => 'prohibited', 'payload' => 'prohibited', 'created_by' => 'prohibited']);

        return (new DeviceSnapshotResource($this->snapshots->capture($target, $request->user(), $data)))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $device, string $snapshot)
    {
        $target = $this->access->findViewableDeviceOrFail($request->user(), $device);

        return new DeviceSnapshotResource($target->snapshots()->with('creator:id,name')->findOrFail($snapshot));
    }

    public function compare(Request $request, string $device)
    {
        $target = $this->access->findViewableDeviceOrFail($request->user(), $device);
        $data = $request->validate(['from_snapshot_id' => 'required|uuid|different:to_snapshot_id', 'to_snapshot_id' => 'required|uuid']);
        $from = $target->snapshots()->findOrFail($data['from_snapshot_id']);
        $to = $target->snapshots()->findOrFail($data['to_snapshot_id']);

        return response()->json(['data' => ['from' => $from->id, 'to' => $to->id, 'differences' => $this->snapshots->compare($from, $to)]]);
    }
}
