<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProvisioningSessionResource;
use App\Models\ProvisioningSession;
use App\Services\ProvisioningSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProvisioningSessionController extends Controller
{
    public function __construct(private ProvisioningSessionService $service) {}

    public function index(Request $request)
    {
        $org = $this->authorizeOrg($request, 'device.view');
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(ProvisioningSession::STATUSES)], 'template_id' => 'nullable|integer', 'device_id' => 'nullable|integer', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'per_page' => 'nullable|integer|in:25,50,100']);
        $query = $org->provisioningSessions()->with(['organization:id,name', 'initiator:id,name,email', 'device:id,name,external_id', 'template:id,name'])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($n) => $n->where('name', 'like', "%{$s}%")->orWhereHas('device', fn ($d) => $d->where('name', 'like', "%{$s}%")->orWhere('external_id', 'like', "%{$s}%"))->orWhereHas('template', fn ($t) => $t->where('name', 'like', "%{$s}%"))))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['template_id'] ?? null, fn ($q, $v) => $q->where('device_template_id', $v))->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('device_id', $v))->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))->latest();
        return ProvisioningSessionResource::collection($query->paginate($filters['per_page'] ?? 25)->withQueryString());
    }

    public function store(Request $request)
    {
        $org = $this->authorizeOrg($request, 'device.manage');
        $data = $request->validate(['name' => 'nullable|string|max:150', 'device_template_id' => 'nullable|integer']);
        return (new ProvisioningSessionResource($this->service->detail($this->service->create($org, $request->user(), $data))))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $session) { $this->authorizeOrg($request, 'device.view'); return new ProvisioningSessionResource($this->service->detail($this->find($request, $session))); }
    public function cancel(Request $request, string $session) { $this->authorizeOrg($request, 'device.manage'); return new ProvisioningSessionResource($this->service->cancel($this->find($request, $session))); }
    public function fail(Request $request, string $session) { $this->authorizeOrg($request, 'device.manage'); $data=$request->validate(['failure_code'=>['required','string','max:80','regex:/^[a-z][a-z0-9_]*$/'],'failure_message'=>'required|string|max:500']); return new ProvisioningSessionResource($this->service->fail($this->find($request,$session),$data['failure_code'],$data['failure_message'])); }
    public function complete(Request $request, string $session) { $this->authorizeOrg($request, 'device.manage'); $data=$request->validate(['name'=>'required|string|max:255','type'=>'required|string|max:100','serialNumber'=>'required|string|max:255|unique:devices,external_id','protocol'=>'required|string|max:50','macAddress'=>'nullable|string|max:50']); return new ProvisioningSessionResource($this->service->complete($this->find($request,$session),$data)); }

    private function authorizeOrg(Request $request, string $permission) { abort_unless($request->user()->organization && $request->user()->hasOrganizationPermission($permission), 403); return $request->user()->organization; }
    private function find(Request $request, string $id): ProvisioningSession { return $request->user()->organization->provisioningSessions()->findOrFail($id); }
}
