<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\ResourceShareRequest;
use App\Services\ResourceSharingService;
use Illuminate\Http\Request;

class AccessRequestController extends Controller
{
    public function __construct(private ResourceSharingService $sharing) {}

    public function index(Request $request)
    {
        $status = $request->validate(['status' => ['nullable', 'in:awaiting_admin_approval,approved,rejected']])['status'] ?? 'awaiting_admin_approval';
        $page = ResourceShareRequest::query()->with(['sender:id,name', 'recipient:id,name,email'])
            ->whereHas('sender', fn ($query) => $query->where('organization_id', $request->user()->organization_id))
            ->where('resource_type', 'device')->where('status', $status)->latest()->paginate(25);
        $devices = Device::query()->whereIn('id', $page->getCollection()->pluck('resource_id'))
            ->get(['id', 'name', 'organization_id'])->keyBy('id');
        $page->setCollection($page->getCollection()->map(function (ResourceShareRequest $share) use ($devices) {
            $device = $devices->get($share->resource_id);
            abort_unless($device, 404);

            return [
                'id' => $share->id,
                'resource' => ['type' => 'device', 'id' => $device->id, 'name' => $device->name, 'organization' => null],
                'sender' => $share->sender ? ['id' => $share->sender->id, 'name' => $share->sender->name] : null,
                'recipient' => $share->recipient ? ['id' => $share->recipient->id, 'name' => $share->recipient->name, 'email' => $share->recipient->email] : null,
                'requested_permission' => $share->requested_permission,
                'final_permission' => $share->final_permission,
                'status' => $share->status,
                'note' => $share->note,
                'created_at' => $share->created_at?->toISOString(),
            ];
        }));

        return response()->json($page);
    }

    public function approve(Request $request, ResourceShareRequest $shareRequest)
    {
        $data = $request->validate(['permission' => ['required', 'in:viewer,full_access']]);
        return response()->json(['data' => $this->sharing->approve($request->user(), $shareRequest, $data['permission'])]);
    }

    public function reject(Request $request, ResourceShareRequest $shareRequest)
    {
        return response()->json(['data' => $this->sharing->reject($request->user(), $shareRequest)]);
    }
}
