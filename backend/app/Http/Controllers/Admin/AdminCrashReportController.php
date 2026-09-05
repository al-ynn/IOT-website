<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CrashReportResource;
use App\Models\CrashReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCrashReportController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate(['search' => 'nullable|string|max:255', 'organization_id' => 'nullable|integer|exists:organizations,id', 'device_id' => 'nullable|integer', 'crash_type' => 'nullable|string|max:80', 'firmware_version' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])]]);
        $q = CrashReport::query()->where('organization_id', $request->user()->organization_id)->when($f['device_id'] ?? null, fn ($x, $v) => $x->where('device_id', $v))->when($f['crash_type'] ?? null, fn ($x, $v) => $x->where('crash_type', $v))->when($f['firmware_version'] ?? null, fn ($x, $v) => $x->where('firmware_version', $v))->when($f['from'] ?? null, fn ($x, $v) => $x->where('received_at', '>=', $v))->when($f['to'] ?? null, fn ($x, $v) => $x->where('received_at', '<=', $v))->when($f['search'] ?? null, function (Builder $x, $v) {
            $x->where(fn ($n) => $n->where('crash_type', 'like', "%{$v}%")->orWhere('reason', 'like', "%{$v}%")->orWhere('message', 'like', "%{$v}%")->orWhere('firmware_version', 'like', "%{$v}%")->orWhereHas('device', fn ($d) => $d->where('name', 'like', "%{$v}%")->orWhere('external_id', 'like', "%{$v}%")));
        });

        return CrashReportResource::collection($q->with(['device:id,name,external_id', 'organization:id,name'])->orderByDesc('received_at')->orderByDesc('id')->paginate($f['per_page'] ?? 25)->withQueryString());
    }

    public function show(Request $request, string $crashReport)
    {
        return new CrashReportResource(CrashReport::where('organization_id', $request->user()->organization_id)->with(['device:id,name,external_id', 'organization:id,name', 'operationalEvent'])->findOrFail($crashReport));
    }
}
