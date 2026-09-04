<?php

namespace App\Http\Controllers;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Dashboard;
use App\Models\ResourceShareRequest;
use App\Services\Dashboard\DashboardService;
use App\Services\ResourceRevisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboards, private CollaborationResourceRegistry $collaboration, private ResourceRevisionService $revisions) {}

    public function index(Request $r)
    {
        return Dashboard::where('owner_user_id', $r->user()->id)->where('scope_type', 'personal')->with('widgets')->get()->map(fn ($d) => $this->dashboards->resource($d, $r->user()));
    }

    public function default(Request $r)
    {
        $d = $this->dashboards->defaultFor($r->user());
        $this->revisions->recordDashboard($d, $r->user(), 'Dashboard baseline');

        return $this->dashboards->resource($d->refresh()->load('widgets'), $r->user());
    }

    public function shares(Request $r, string $dashboard)
    {
        $resolved = $this->collaboration->resolve($r->user(), new CollaborationResourceReference('dashboard', $dashboard), 'share');
        abort_unless($resolved->resource->scope_type === 'personal', 404);
        $items = ResourceShareRequest::where(['resource_type' => 'dashboard', 'resource_id' => $resolved->resource->id])->with(['sender:id,name', 'recipient:id,name,email'])->latest()->paginate(25);

        return response()->json($items->through(fn ($s) => ['id' => $s->id, 'recipient' => $s->recipient ? ['id' => $s->recipient->id, 'name' => $s->recipient->name, 'email' => $s->recipient->email] : null, 'permission' => $s->final_permission ?? $s->requested_permission, 'status' => $s->status, 'createdAt' => $s->created_at?->toISOString(), 'acceptedAt' => $s->accepted_at?->toISOString()]));
    }

    public function show(Request $r, string $dashboard)
    {
        $resolved = $this->collaboration->resolve($r->user(), new CollaborationResourceReference('dashboard', $dashboard), 'view');
        $d = $resolved->resource;
        abort_unless($d->scope_type === 'personal', 404);
        $this->revisions->recordDashboard($d, $d->owner, 'Dashboard baseline');
        $edit = $resolved->definition->authorizer->canEdit($r->user(), $d);

        return $this->dashboards->resource($d->refresh()->load('widgets'), $r->user(), $edit, $resolved->definition->authorizer->canShare($r->user(), $d));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $d = Dashboard::create(['organization_id' => $r->user()->organization_id, 'owner_user_id' => $r->user()->id, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'scope_type' => 'personal', 'created_by' => $r->user()->id, 'updated_by' => $r->user()->id]);

        return response()->json($this->dashboards->resource($this->dashboards->save($r->user(), $d, $data), $r->user()), 201);
    }

    public function update(Request $r, string $dashboard)
    {
        $resolved = $this->collaboration->resolve($r->user(), new CollaborationResourceReference('dashboard', $dashboard), 'edit');
        $d = $resolved->resource;
        abort_unless($d->scope_type === 'personal', 404);

        return $this->dashboards->resource($this->dashboards->save($r->user(), $d, $this->validated($r), null, $r->input('layoutVersion'), $r->input('baseRevisionId'), $r->header('Idempotency-Key')), $r->user(), true, $resolved->definition->authorizer->canShare($r->user(), $d));
    }

    public function destroy(Request $r, string $dashboard)
    {
        $d = $this->owned($r, $dashboard, 'personal');
        abort_if($d->is_default, 422, 'The default Dashboard cannot be deleted.');
        $d->delete();

        return response()->noContent();
    }

    public function duplicate(Request $r, string $dashboard)
    {
        $source = $this->owned($r, $dashboard, 'personal');
        $data = ['name' => $source->name.' Copy', 'description' => $source->description, 'widgets' => $source->widgets->map(fn ($w) => ['id' => (string) Str::uuid(), 'type' => $w->widget_type, 'settings' => ['title' => $w->title, 'datasource' => isset($w->configuration['deviceId']) ? ['deviceId' => (string) $w->configuration['deviceId'], 'telemetryKey' => $w->configuration['telemetryKey'] ?? '', 'unit' => $w->configuration['unit'] ?? null] : null, 'timeRange' => $w->configuration['timeRange'] ?? null, 'chartType' => $w->configuration['chartType'] ?? null, 'minimum' => $w->configuration['minimum'] ?? null, 'maximum' => $w->configuration['maximum'] ?? null], 'layout' => $w->layout])->all()];
        $r->merge($data);

        return $this->store($r);
    }

    public function globalDefault(Request $r)
    {
        $this->admin($r);

        return $this->dashboards->resource($this->dashboards->defaultFor($r->user(), 'admin_global'), $r->user());
    }

    public function globalUpdate(Request $r)
    {
        $this->admin($r);
        $d = $this->dashboards->defaultFor($r->user(), 'admin_global');

        return $this->dashboards->resource($this->dashboards->save($r->user(), $d, $this->validated($r)), $r->user());
    }

    private function owned(Request $r, string $id, string $scope): Dashboard
    {
        return Dashboard::where('owner_user_id', $r->user()->id)->where('scope_type', $scope)->with('widgets')->findOrFail($id);
    }

    private function admin(Request $r): void
    {
        abort_unless($r->user()->isPlatformAdmin(), 403);
    }

    private function validated(Request $r): array
    {
        return $r->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'widgets' => ['present', 'array', 'max:30'], 'widgets.*' => ['array'], 'baseRevisionId' => ['nullable', 'integer'], 'layoutVersion' => ['nullable', 'integer', 'min:1']]);
    }
}
