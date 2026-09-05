<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\ResourceCollaborator;
use App\Services\ReportAccessService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private ReportService $service, private ReportAccessService $access) {}

    public function index(Request $request)
    {
        $org = $this->org($request);
        $f = $request->validate(['search' => 'nullable|string|max:150', 'report_type' => ['nullable', Rule::in(Report::TYPES)], 'per_page' => 'nullable|integer|in:25,50,100']);
        $ids = $request->user()->isPlatformAdmin() ? $org->reports()->select('id') : ResourceCollaborator::where(['resource_type' => 'report', 'user_id' => $request->user()->id])->select('resource_id');
        $q = $org->reports()->whereIn('id', $ids)->with(['creator:id,name', 'updater:id,name', 'runs' => fn ($x) => $x->latest()->limit(1)])->when($f['search'] ?? null, fn ($x, $v) => $x->where(fn ($n) => $n->where('name', 'like', "%{$v}%")->orWhere('description', 'like', "%{$v}%")->orWhere('report_type', 'like', "%{$v}%")))->when($f['report_type'] ?? null, fn ($x, $v) => $x->where('report_type', $v))->latest('updated_at');

        return ReportResource::collection($q->paginate($f['per_page'] ?? 25)->withQueryString());
    }

    public function store(Request $request)
    {
        $this->org($request);
        $data = $this->validated($request, true);

        return (new ReportResource($this->service->create($request->user(), $data)->load(['creator:id,name', 'updater:id,name', 'runs'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $report)
    {
        return new ReportResource($this->find($request, $report)->load(['creator:id,name', 'updater:id,name', 'runs' => fn ($q) => $q->latest()->limit(1)]));
    }

    public function update(Request $request, string $report)
    {
        $model = $this->find($request, $report);
        abort_unless($this->access->canEdit($request->user(), $model), 404);
        $data = $this->validated($request, false);

        return new ReportResource($this->service->update($request->user(), $model, $data, $request->input('baseRevisionId'), $request->header('Idempotency-Key'))->load(['creator:id,name', 'updater:id,name', 'runs' => fn ($q) => $q->latest()->limit(1)]));
    }

    public function destroy(Request $request, string $report)
    {
        $model = $this->find($request, $report);
        abort_unless($this->access->canEdit($request->user(), $model), 404);
        $this->service->delete($model);

        return response()->noContent();
    }

    private function validated(Request $request, bool $create): array
    {
        return $request->validate(['name' => [$create ? 'required' : 'sometimes', 'string', 'max:150'], 'description' => 'nullable|string|max:500', 'report_type' => [$create ? 'required' : 'sometimes', Rule::in(Report::TYPES)], 'configuration' => [$create ? 'required' : 'sometimes', 'array'], 'baseRevisionId'=>[$create?'prohibited':'nullable','integer'], 'organization_id' => 'prohibited', 'created_by' => 'prohibited', 'updated_by' => 'prohibited']);
    }

    private function org(Request $request)
    {
        abort_unless($request->user()->organization && $request->user()->hasOrganizationPermission('analytics.view'), 403);

        return $request->user()->organization;
    }

    private function find(Request $request, string $id): Report
    {
        $report = $this->org($request)->reports()->findOrFail($id);
        abort_unless($this->access->canView($request->user(), $report), 404);
        return $report;
    }
}
