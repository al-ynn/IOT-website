<?php

namespace App\Http\Controllers;

use App\Models\ResourceShareRequest;
use App\Services\ResourceSharingService;
use Illuminate\Http\Request;

class ResourceShareController extends Controller
{
    public function __construct(private ResourceSharingService $sharing) {}

    public function store(Request $request)
    {
        $data = $request->validate(['resource_type' => ['required', 'string', 'max:64'], 'resource_id' => ['required', 'integer', 'min:1'], 'recipient_id' => ['required', 'integer', 'min:1'], 'permission' => ['required', 'string', 'max:32'], 'note' => ['nullable', 'string', 'max:1000']]);
        return response()->json(['data' => $this->payload($this->sharing->create($request->user(), $data))], 201);
    }

    public function incoming(Request $request)
    {
        return response()->json($this->sharing->visibleTo($request->user())->where('recipient_user_id', $request->user()->id)->latest()->paginate(25)->through(fn ($share) => $this->payload($share)));
    }

    public function sent(Request $request)
    {
        return response()->json($this->sharing->visibleTo($request->user())->where('sender_user_id', $request->user()->id)->latest()->paginate(25)->through(fn ($share) => $this->payload($share)));
    }

    public function show(Request $request, ResourceShareRequest $shareRequest)
    {
        abort_unless((int) $shareRequest->sender_user_id === (int) $request->user()->id || (int) $shareRequest->recipient_user_id === (int) $request->user()->id || $request->user()->isPlatformAdmin(), 404);
        return response()->json(['data' => $this->payload($shareRequest->load(['sender:id,name', 'recipient:id,name,email', 'approver:id,name', 'rejector:id,name']))]);
    }

    public function accept(Request $request, ResourceShareRequest $shareRequest) { return response()->json(['data' => $this->payload($this->sharing->accept($request->user(), $shareRequest))]); }
    public function decline(Request $request, ResourceShareRequest $shareRequest) { return response()->json(['data' => $this->payload($this->sharing->decline($request->user(), $shareRequest))]); }
    public function cancel(Request $request, ResourceShareRequest $shareRequest) { return response()->json(['data' => $this->payload($this->sharing->cancel($request->user(), $shareRequest))]); }
    public function revoke(Request $request, ResourceShareRequest $shareRequest) { return response()->json(['data' => $this->payload($this->sharing->revoke($request->user(), $shareRequest))]); }
    public function permission(Request $request, ResourceShareRequest $shareRequest) { $data=$request->validate(['permission'=>['required','in:view,edit']]); return response()->json(['data'=>$this->payload($this->sharing->changeDashboardPermission($request->user(),$shareRequest,$data['permission']))]); }

    public function candidates(Request $request)
    {
        $data = $request->validate(['resource_type' => ['required', 'string'], 'resource_id' => ['required', 'integer'], 'search' => ['nullable', 'string', 'max:100']]);
        $resolved = app(\App\Collaboration\CollaborationResourceRegistry::class)->resolve($request->user(), new \App\Collaboration\CollaborationResourceReference($data['resource_type'], $data['resource_id']), 'share');
        abort_unless(in_array($data['resource_type'], ['device', 'device_template', 'dashboard', 'automation', 'report', 'location'], true), 422);
        $resource = $resolved->resource;
        $users = \App\Models\User::query()->where('organization_id', $resource->organization_id)->where('status', 'active')->where(fn ($q) => $q->whereNull('platform_role')->orWhere('platform_role', '!=', 'platform_admin'))->whereKeyNot($request->user()->id)
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($nested) => $nested->where('name', 'like', '%'.trim($search).'%')->orWhere('email', 'like', '%'.trim($search).'%')))
            ->with(['deviceAccessAssignments' => fn ($q) => $q->where('device_id', $data['resource_type'] === 'device' ? $resource->id : 0)])->orderBy('name')->limit(20)->get()
            ->map(function ($user) use ($data, $resource) {
                $existing = $data['resource_type'] === 'device'
                    ? $user->deviceAccessAssignments->first()?->access_level
                    : \App\Models\ResourceCollaborator::where(['resource_type' => $data['resource_type'], 'resource_id' => $resource->id, 'user_id' => $user->id])->value('permission');
                return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'existing_access' => $existing];
            });
        return response()->json(['data' => $users]);
    }

    private function payload(ResourceShareRequest $share): array
    {
        $model = match ($share->resource_type) { 'device_template' => \App\Models\DeviceTemplate::class, 'automation' => \App\Models\Automation::class, 'report' => \App\Models\Report::class, 'location' => \App\Models\Location::class, 'dashboard' => \App\Models\Dashboard::class, default => \App\Models\Device::class };
        $resource = $model::query()->select(['id', 'name', 'organization_id'])->with('organization:id,name')->find($share->resource_id);
        return ['id' => $share->id, 'resource' => ['type' => $share->resource_type, 'id' => $share->resource_id, 'name' => $resource?->name, 'organization' => $resource?->organization?->name], 'sender' => $share->sender ? ['id' => $share->sender->id, 'name' => $share->sender->name] : null, 'recipient' => $share->recipient ? ['id' => $share->recipient->id, 'name' => $share->recipient->name, 'email' => $share->recipient->email] : null, 'requested_permission' => $share->requested_permission, 'final_permission' => $share->final_permission, 'status' => $share->status, 'note' => $share->note, 'accepted_at' => $share->accepted_at, 'declined_at' => $share->declined_at, 'approved_at' => $share->approved_at, 'rejected_at' => $share->rejected_at, 'cancelled_at' => $share->cancelled_at, 'created_at' => $share->created_at];
    }
}
