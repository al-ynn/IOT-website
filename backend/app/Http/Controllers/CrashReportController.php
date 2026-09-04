<?php

namespace App\Http\Controllers;

use App\Http\Resources\CrashReportResource;
use App\Models\CrashReport;
use App\Services\Admin\DeviceAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrashReportController extends Controller
{
    public function __construct(private DeviceAccessService $access) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->scoped($request);
        $this->apply($query, $filters);

        return CrashReportResource::collection($query->with(['device:id,name,external_id', 'organization:id,name'])->orderByDesc('received_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 25)->withQueryString());
    }

    public function show(Request $request, string $crashReport)
    {
        return new CrashReportResource($this->scoped($request)->with(['device:id,name,external_id', 'organization:id,name', 'operationalEvent'])->findOrFail($crashReport));
    }

    private function scoped(Request $request): Builder
    {
        $user = $request->user();
        abort_unless($user->organization && $user->hasOrganizationPermission('device.view'), 403);
        $ids = $this->access->accessibleDevices($user)->select('devices.id');

        return CrashReport::query()->where('organization_id', $user->organization_id)->whereIn('device_id', $ids);
    }

    private function filters(Request $request): array
    {
        return $request->validate(['search' => 'nullable|string|max:255', 'device_id' => 'nullable|integer', 'crash_type' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_.-]*$/'], 'firmware_version' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])], 'page' => 'nullable|integer|min:1']);
    }

    private function apply(Builder $query, array $filters): void
    {
        $query->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('device_id', $v))->when($filters['crash_type'] ?? null, fn ($q, $v) => $q->where('crash_type', $v))->when($filters['firmware_version'] ?? null, fn ($q, $v) => $q->where('firmware_version', $v))->when($filters['from'] ?? null, fn ($q, $v) => $q->where('received_at', '>=', $v))->when($filters['to'] ?? null, fn ($q, $v) => $q->where('received_at', '<=', $v))->when($filters['search'] ?? null, function ($q, $v) {
            $q->where(fn ($n) => $n->where('crash_type', 'like', "%{$v}%")->orWhere('reason', 'like', "%{$v}%")->orWhere('message', 'like', "%{$v}%")->orWhere('firmware_version', 'like', "%{$v}%")->orWhereHas('device', fn ($d) => $d->where('name', 'like', "%{$v}%")->orWhere('external_id','like',"%{$v}%")));
        });
    }
}
