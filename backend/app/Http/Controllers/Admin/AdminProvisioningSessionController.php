<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProvisioningSessionResource;
use App\Models\ProvisioningSession;
use App\Services\ProvisioningSessionService;
use Illuminate\Http\Request;

class AdminProvisioningSessionController extends Controller
{
    public function __construct(private ProvisioningSessionService $service) {}

    public function index(Request $request)
    {
        $data = $request->validate(['organization_id' => 'nullable|integer', 'status' => 'nullable|in:pending,completed,failed,expired,cancelled', 'per_page' => 'nullable|integer|in:25,50,100']);
        $query = ProvisioningSession::query()->where('organization_id', $request->user()->organization_id)->with(['organization:id,name', 'initiator:id,name,email', 'device:id,name,external_id', 'template:id,name'])->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->latest();

        return ProvisioningSessionResource::collection($query->paginate($data['per_page'] ?? 25)->withQueryString());
    }

    public function show(Request $request, string $session)
    {
        return new ProvisioningSessionResource($this->service->detail(ProvisioningSession::query()->where('organization_id', $request->user()->organization_id)->findOrFail($session)));
    }
}
