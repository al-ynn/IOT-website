<?php

namespace App\Http\Controllers\Automation;

use App\Collaboration\AutomationCollaborationAuthorizer;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationExecutionLog;
use App\Models\Device;
use App\Models\ResourceCollaborator;
use App\Models\ResourceRevision;
use App\Services\Admin\DeviceAccessService;
use App\Services\Automation\AutomationDefinitionService;
use App\Services\Automation\AutomationDefinitionValidator;
use App\Services\Automation\AutomationExecutionService;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function __construct(private AutomationDefinitionService $definitions, private AutomationDefinitionValidator $validator, private DeviceAccessService $deviceAccess, private AutomationCollaborationAuthorizer $authorizer) {}

    public function index(Request $r)
    {
        $org = $r->user()->organization;
        abort_unless($org, 403);
        $filters = $r->validate(['search' => 'nullable|string|max:255', 'status' => 'nullable|in:enabled,disabled', 'trigger_type' => 'nullable|in:telemetry,device_status,schedule,manual', 'device_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|in:25,50,100']);
        $ids = $r->user()->isPlatformAdmin() ? $org->automations()->select('id') : ResourceCollaborator::where(['resource_type' => 'automation', 'user_id' => $r->user()->id])->select('resource_id');
        $query = $org->automations()->whereIn('id', $ids)->with(['triggers', 'conditions', 'actions', 'schedules'])->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($n) => $n->where('name', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))->when(isset($filters['status']), fn ($q) => $q->where('enabled', $filters['status'] === 'enabled'))->when($filters['trigger_type'] ?? null, fn ($q, $type) => $q->whereHas('triggers', fn ($t) => $t->where('type', $type)))->when($filters['device_id'] ?? null, fn ($q, $id) => $q->whereHas('triggers', fn ($t) => $t->where('configuration->deviceId', (int) $id)))->latest();
        $page = $query->paginate($filters['per_page'] ?? 25)->withQueryString();
        $devices = $this->devicesFor($page->getCollection());

        return $page->through(fn ($a) => $this->resource($a, $devices, $r));
    }

    public function store(Request $r)
    {
        $org = $this->access($r, 'automation.create');
        $data = $this->validator->validate($r->all(), $org, false, $r->user(), true);

        return response()->json($this->resource($this->definitions->create($org, $r->user(), $data), [], $r), 201);
    }

    public function show(Request $r, string $automation)
    {
        $a = $this->find($automation);
        abort_unless($this->authorizer->canView($r->user(), $a), 404);

        return $this->resource($a, [], $r);
    }

    public function update(Request $r, string $automation)
    {
        $a = $this->find($automation);
        abort_unless($this->authorizer->canEdit($r->user(), $a), 404);
        $submitted = $r->except(['baseRevisionId']);
        $merged = array_replace_recursive($this->definitions->definition($a), $submitted);
        $data = $this->validator->validate($merged, $a->organization, false, $r->user(), true);
        $command = array_intersect_key($data, $submitted);

        return $this->resource($this->definitions->update($a, $r->user(), $data, $r->input('baseRevisionId'), $r->header('Idempotency-Key'), $command), [], $r);
    }

    public function destroy(Request $r, string $automation)
    {
        $a = $this->find($automation);
        $this->manage($r, $a);
        $a->delete();

        return response()->noContent();
    }

    public function enable(Request $r, string $automation)
    {
        return $this->toggle($r, $automation, true);
    }

    public function disable(Request $r, string $automation)
    {
        return $this->toggle($r, $automation, false);
    }

    public function execute(Request $r, string $automation, AutomationExecutionService $service)
    {
        $a = $this->find($automation);
        $this->manage($r, $a);
        $data = $r->validate(['data' => 'sometimes|array', 'correlationId' => 'sometimes|uuid']);

        return response()->json($this->execution($service->execute($a, 'manual', $data['data'] ?? [], $data['correlationId'] ?? null)));
    }

    public function executions(Request $r, string $automation)
    {
        $a = $this->find($automation);
        $this->runtimeView($r, $a);

        return $a->executions()->latest()->paginate(min((int) $r->input('perPage', 20), 100))->through(fn ($e) => $this->execution($e));
    }

    public function logs(Request $r, string $automation)
    {
        $a = $this->find($automation);
        $this->runtimeView($r, $a);

        return AutomationExecutionLog::whereHas('execution', fn ($q) => $q->where('automation_id', $a->id)->where('organization_id', $a->organization_id))->latest('executed_at')->paginate(50);
    }

    private function toggle(Request $r, string $id, bool $enabled)
    {
        $a = $this->find($id);
        $this->manage($r, $a);
        if ($enabled) {
            $this->validator->validate($this->definitions->definition($a), $a->organization, false, $r->user(), true);
        }$a->update(['enabled' => $enabled, 'status' => $enabled ? 'active' : 'disabled', 'updated_by' => $r->user()->id]);

        return $this->resource($a->refresh()->load(['triggers', 'conditions', 'actions', 'schedules']), [], $r);
    }

    private function access(Request $r, string $permission)
    {
        abort_unless($r->user()->organization && $r->user()->hasOrganizationPermission($permission), 403);

        return $r->user()->organization;
    }

    private function find(string $id): Automation
    {
        return Automation::with(['organization', 'triggers', 'conditions', 'actions', 'schedules'])->findOrFail($id);
    }

    private function manage(Request $r, Automation $a): void
    {
        abort_unless($this->authorizer->canEdit($r->user(), $a), 404);
        abort_unless(in_array($a->id, $this->deviceAccess->accessibleAutomationIds($r->user()), true), 404);
        abort_unless($this->deviceAccess->canManageAutomation($r->user(), $a), 403, 'Full device access is required.');
    }

    private function runtimeView(Request $r, Automation $a): void
    {
        abort_unless($this->authorizer->canView($r->user(), $a) && in_array($a->id, $this->deviceAccess->accessibleAutomationIds($r->user()), true), 404);
    }

    private function resource(Automation $a, array $devices = [], ?Request $r = null): array
    {
        $d = $this->definitions->definition($a);
        $deviceId = $d['trigger']['deviceId'] ?? null;
        $device = $deviceId !== null ? ($devices[(string) $deviceId] ?? Device::where('organization_id', $a->organization_id)->find($deviceId)) : null;
        $targetVisible = $device && $r && $this->deviceAccess->canViewDevice($r->user(), $device);
        $baseRevisionId = ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $a->id])->orderByDesc('revision_number')->value('id');

        return ['id' => (string) $a->id, 'name' => $a->name, 'description' => $a->description, 'status' => $a->status, 'enabled' => $a->enabled, 'version' => $a->version, 'baseRevisionId' => $baseRevisionId ? (string) $baseRevisionId : null, 'saveOutcome' => $a->getAttribute('save_idempotency_outcome'), 'trigger' => $d['trigger'], 'triggerDevice' => $deviceId === null ? null : ['id' => (string) $deviceId, 'name' => $targetVisible ? $device->name : null, 'available' => $targetVisible], 'conditions' => $d['conditions'], 'actions' => $d['actions'], 'schedule' => $d['schedule'], 'capabilities' => ['canView' => ! $r || $this->authorizer->canView($r->user(), $a), 'canEdit' => (bool) $r && $this->authorizer->canEdit($r->user(), $a), 'canShare' => (bool) $r && $this->authorizer->canShare($r->user(), $a), 'canExecute' => (bool) $r && $this->authorizer->canEdit($r->user(), $a) && $this->deviceAccess->canManageAutomation($r->user(), $a)], 'lastExecutedAt' => $a->last_executed_at?->toISOString(), 'createdAt' => $a->created_at->toISOString(), 'updatedAt' => $a->updated_at->toISOString()];
    }

    private function devicesFor($as): array
    {
        $ids = $as->map(fn ($a) => $a->triggers->first()?->configuration['deviceId'] ?? null)->filter()->unique();

        return Device::whereIn('id', $ids)->get(['id', 'name', 'organization_id'])->keyBy(fn ($d) => (string) $d->id)->all();
    }

    private function execution($e): array
    {
        return ['id' => (string) $e->id, 'automationId' => (string) $e->automation_id, 'status' => $e->status, 'triggerType' => $e->trigger_type, 'triggerData' => $e->trigger_data ?? [], 'startedAt' => $e->started_at?->toISOString(), 'completedAt' => $e->completed_at?->toISOString(), 'error' => $this->safeError($e->error_message), 'result' => $e->result_summary];
    }

    private function safeError(?string $error): ?string
    {
        if ($error === null) {
            return null;
        }

return in_array($error,['Automation is disabled.', 'One or more actions failed.'],true) ? $error : 'Automation execution failed.';
    }
}
