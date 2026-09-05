<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OperationalEventResource;
use App\Models\OperationalEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminOperationalEventController extends Controller
{
    public function index(Request $r)
    {
        $f = $this->filters($r);
        $q = OperationalEvent::query()->where('organization_id', $r->user()->organization_id);
        $this->apply($q, $f);

        return OperationalEventResource::collection($q->with(['device:id,name', 'organization:id,name', 'automation:id,name'])->orderByDesc('occurred_at')->orderByDesc('id')->paginate($f['per_page'] ?? 25)->withQueryString());
    }

    public function show(Request $r, string $event)
    {
        return new OperationalEventResource(OperationalEvent::where('organization_id', $r->user()->organization_id)->with(['device:id,name', 'organization:id,name', 'automation:id,name'])->findOrFail($event));
    }

    private function filters(Request $r): array
    {
        return $r->validate(['search' => ['nullable', 'string', 'max:255'], 'organization_id' => ['nullable', 'integer', 'exists:organizations,id'], 'severity' => ['nullable', Rule::in(OperationalEvent::SEVERITIES)], 'source' => ['nullable', Rule::in(OperationalEvent::SOURCES)], 'event_type' => ['nullable', Rule::in(OperationalEvent::TYPES)], 'device_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])], 'page' => ['nullable', 'integer', 'min:1']]);
    }

    private function apply(Builder $q, array $f): void
    {
        $q->when($f['severity'] ?? null, fn ($x, $v) => $x->where('severity', $v))->when($f['source'] ?? null, fn ($x, $v) => $x->where('source', $v))->when($f['event_type'] ?? null, fn ($x, $v) => $x->where('event_type', $v))->when($f['device_id'] ?? null, fn ($x, $v) => $x->where('device_id', $v))->when($f['from'] ?? null, fn ($x, $v) => $x->where('occurred_at', '>=', $v))->when($f['to'] ?? null, fn ($x, $v) => $x->where('occurred_at', '<=', $v))->when($f['search'] ?? null, function ($x, $v) {
            $x->where(fn ($n) => $n->where('title', 'like', "%{$v}%")->orWhere('message', 'like', "%{$v}%")->orWhere('event_type', 'like', "%{$v}%")->orWhereHas('device', fn ($d) => $d->where('name','like',"%{$v}%")));
        });
    }
}
