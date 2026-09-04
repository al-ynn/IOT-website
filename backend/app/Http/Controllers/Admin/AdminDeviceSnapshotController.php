<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceSnapshotResource;
use App\Models\Device;
use App\Services\DeviceSnapshotService;
use Illuminate\Http\Request;

class AdminDeviceSnapshotController extends Controller
{
    public function __construct(private DeviceSnapshotService $snapshots) {}

    public function index(Request $request, Device $device)
    {
        $filters = $request->validate(['per_page' => 'nullable|integer|in:25,50,100']);

        return DeviceSnapshotResource::collection($device->snapshots()->with('creator:id,name')->latest('captured_at')->paginate($filters['per_page'] ?? 25));
    }

    public function store(Request $request, Device $device)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'description' => 'nullable|string|max:1000', 'device_id' => 'prohibited', 'organization_id' => 'prohibited', 'payload' => 'prohibited', 'created_by' => 'prohibited']);

        return (new DeviceSnapshotResource($this->snapshots->capture($device, $request->user(), $data)))->response()->setStatusCode(201);
    }

    public function show(Device $device, string $snapshot)
    {
        return new DeviceSnapshotResource($device->snapshots()->with('creator:id,name')->findOrFail($snapshot));
    }

    public function compare(Request $request, Device $device)
    {
        $data = $request->validate(['from_snapshot_id' => 'required|uuid|different:to_snapshot_id', 'to_snapshot_id' => 'required|uuid']);
        $from = $device->snapshots()->findOrFail($data['from_snapshot_id']);
        $to = $device->snapshots()->findOrFail($data['to_snapshot_id']);

        return response()->json(['data' => ['from' => $from->id, 'to' => $to->id, 'differences' => $this->snapshots->compare($from, $to)]]);
    }
}
