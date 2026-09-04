<?php

namespace App\Services;

use App\Lifecycle\ResourceLifecyclePolicyRegistry;
use App\Models\Device;
use App\Models\Dashboard;
use App\Models\Automation;
use App\Models\Report;
use App\Models\Webhook;
use App\Models\DeviceTemplate;
use App\Models\FirmwareArtifact;
use App\Models\Location;
use App\Models\ResourceAttentionState;
use App\Models\ResourceLifecycleState;
use App\Models\ResourcePublicationState;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AdminDisabledResourceService
{
    public function __construct(private ResourceLifecyclePolicyRegistry $policies) {}

    public function index(User $admin, array $filters): LengthAwarePaginator
    {
        $filters['organization_id'] = $admin->organization_id;
        $this->authorize($admin, $filters);
        $state = $filters['state'] ?? 'disabled';
        $query = ResourceLifecycleState::query()->where('state', $state)->with('disabler:id,name,status');
        if (isset($filters['resource_type'])) $query->where('resource_type', $filters['resource_type']);
        if (isset($filters['disabled_by'])) $query->where('disabled_by', $filters['disabled_by']);
        $this->filterResources($query, $filters);
        if (isset($filters['needs_attention'])) {
            $wanted = filter_var($filters['needs_attention'], FILTER_VALIDATE_BOOLEAN);
            $method = $wanted ? 'whereExists' : 'whereNotExists';
            $query->{$method}(fn ($attention) => $attention->selectRaw('1')->from('resource_attention_states as ras')->whereColumn('ras.resource_type', 'resource_lifecycle_states.resource_type')->whereColumn('ras.resource_id', 'resource_lifecycle_states.resource_id')->where('ras.status', 'open'));
        }
        if (isset($filters['has_publication'])) {
            $wanted = filter_var($filters['has_publication'], FILTER_VALIDATE_BOOLEAN);
            $method = $wanted ? 'whereExists' : 'whereNotExists';
            $query->{$method}(fn ($publication) => $publication->selectRaw('1')->from('resource_publication_states as rps')->whereColumn('rps.resource_type', 'resource_lifecycle_states.resource_type')->whereColumn('rps.resource_id', 'resource_lifecycle_states.resource_id')->whereNotNull('rps.current_publication_version_id'));
        }
        $sort = $filters['sort'] ?? 'newest';
        if ($sort === 'name') $query->orderBy('resource_type')->orderBy('resource_id');
        else $query->orderBy($state === 'archived' ? 'archived_at' : 'disabled_at', $sort === 'oldest' ? 'asc' : 'desc');
        $page = $query->orderBy('id')->paginate((int) ($filters['per_page'] ?? 25));
        return $this->hydrate($page);
    }

    public function summary(User $admin): array
    {
        abort_unless($admin->isPlatformAdmin(), 403);
        $query = ResourceLifecycleState::query()->whereIn('resource_type', $this->policies->types());
        $this->filterResources($query, ['organization_id' => $admin->organization_id]);
        $counts = $query->selectRaw('state, resource_type, COUNT(*) as aggregate')->groupBy('state', 'resource_type')->get();
        return ['totalDisabled' => (int) $counts->where('state', 'disabled')->sum('aggregate'), 'disabledByType' => collect($this->policies->types())->mapWithKeys(fn ($type) => [$type => (int) ($counts->first(fn ($row) => $row->state === 'disabled' && $row->resource_type === $type)?->aggregate ?? 0)])->all(), 'totalArchived' => (int) $counts->where('state', 'archived')->sum('aggregate')];
    }

    private function filterResources($query, array $filters): void
    {
        if (!isset($filters['organization_id']) && trim((string) ($filters['search'] ?? '')) === '') return;
        $query->where(function ($outer) use ($filters) {
            foreach ($this->selectedTypes($filters) as $type) {
                $table = match($type){'device'=>'devices','device_template'=>'device_templates','firmware'=>'firmware_artifacts','location'=>'locations','dashboard'=>'dashboards','automation'=>'automations','report'=>'reports','webhook'=>'webhooks'};
                $outer->orWhere(fn ($part) => $part->where('resource_type', $type)->whereExists(function ($resource) use ($table, $filters) {
                    $resource->selectRaw('1')->from($table.' as resource')->leftJoin('organizations as org', 'org.id', '=', 'resource.organization_id')->whereColumn('resource.id', 'resource_lifecycle_states.resource_id');
                    if (isset($filters['organization_id'])) $resource->where('resource.organization_id', $filters['organization_id']);
                    if ($search = trim((string) ($filters['search'] ?? ''))) $resource->where(fn ($searchQuery) => $searchQuery->where('resource.name', 'like', "%{$search}%")->orWhere('org.name', 'like', "%{$search}%"));
                }));
            }
        });
    }

    private function hydrate(LengthAwarePaginator $page): LengthAwarePaginator
    {
        $rows = collect($page->items());
        $devices = Device::whereIn('id', $rows->where('resource_type', 'device')->pluck('resource_id'))->with(['organization:id,name', 'creator:id,name,status'])->withCount('accessAssignments')->get()->keyBy('id');
        $templates = DeviceTemplate::whereIn('id', $rows->where('resource_type', 'device_template')->pluck('resource_id'))->with(['organization:id,name', 'creator:id,name,status'])->withCount('collaborators')->get()->keyBy('id');
        $firmware = FirmwareArtifact::whereIn('id',$rows->where('resource_type','firmware')->pluck('resource_id'))->with(['organization:id,name','creator:id,name,status','currentRelease'])->withCount('collaborators')->get()->keyBy('id');
        $locations = Location::whereIn('id',$rows->where('resource_type','location')->pluck('resource_id'))->with(['organization:id,name','creator:id,name,status'])->withCount('devices')->get()->keyBy('id');
        $dashboards = Dashboard::whereIn('id',$rows->where('resource_type','dashboard')->pluck('resource_id'))->with(['organization:id,name','creator:id,name,status'])->withCount('collaborators')->get()->keyBy('id');
        $automations = Automation::whereIn('id',$rows->where('resource_type','automation')->pluck('resource_id'))->with(['organization:id,name','creator:id,name,status'])->withCount('collaborators')->get()->keyBy('id');
        $reports = Report::whereIn('id',$rows->where('resource_type','report')->pluck('resource_id'))->with(['organization:id,name','creator:id,name,status'])->withCount('collaborators')->get()->keyBy('id');
        $webhooks = Webhook::whereIn('id',$rows->where('resource_type','webhook')->pluck('resource_id'))->with(['organization:id,name','creator:id,name,status'])->withCount('collaborators')->get()->keyBy('id');
        $attention = $this->related(ResourceAttentionState::query()->where('status', 'open'), $rows)->get()->keyBy(fn ($row) => "{$row->resource_type}:{$row->resource_id}");
        $publication = $this->related(ResourcePublicationState::query()->with('currentVersion:id,version_number'), $rows)->get()->keyBy(fn ($row) => "{$row->resource_type}:{$row->resource_id}");
        $page->setCollection($rows->map(fn ($row) => $this->item($row, $devices, $templates,$firmware,$locations,$dashboards,$automations,$reports,$webhooks, $attention, $publication))->filter()->values());
        return $page;
    }

    private function item(ResourceLifecycleState $row, Collection $devices, Collection $templates,Collection $firmware,Collection $locations,Collection $dashboards,Collection $automations,Collection $reports,Collection $webhooks, Collection $attention, Collection $publication): ?array
    {
        $resource = match($row->resource_type){'device'=>$devices->get($row->resource_id),'device_template'=>$templates->get($row->resource_id),'firmware'=>$firmware->get($row->resource_id),'location'=>$locations->get($row->resource_id),'dashboard'=>$dashboards->get($row->resource_id),'automation'=>$automations->get($row->resource_id),'report'=>$reports->get($row->resource_id),'webhook'=>$webhooks->get($row->resource_id)};
        if (!$resource) return null;
        $key = "{$row->resource_type}:{$row->resource_id}";
        $published = $publication->get($key)?->currentVersion;
        return ['resourceType' => $row->resource_type, 'resourceId' => (string) $resource->id, 'resourceLabel' => $resource->name, 'organization' => $resource->organization ? ['id' => (string) $resource->organization->id, 'name' => $resource->organization->name] : null, 'lifecycle' => $row->state, 'disabledAt' => $row->disabled_at?->toISOString(), 'archivedAt' => $row->archived_at?->toISOString(), 'disabledBy' => $row->disabler ? ['id' => (string) $row->disabler->id, 'name' => $row->disabler->name, 'inactive' => $row->disabler->status !== 'active'] : null, 'creator' => $resource->creator ? ['id' => (string) $resource->creator->id, 'name' => $resource->creator->name] : null, 'createdAt' => $resource->created_at?->toISOString(), 'needsAttention' => $attention->has($key), 'publication' => $published ? ['version' => $published->version_number] : null, 'accessSummary' => $row->resource_type === 'device' ? ['label' => 'assignments', 'count' => $resource->access_assignments_count] : ($row->resource_type==='location'?['label'=>'devices','count'=>$resource->devices_count]:['label' => 'collaborators', 'count' => $resource->collaborators_count]), 'capabilities' => ['canRestore' => true, 'restoreBlocker' => null], 'adminDeepLink' => match($row->resource_type){'device'=>"/admin/devices/{$resource->id}",'device_template'=>"/admin/templates/{$resource->id}",'location'=>"/admin/locations/{$resource->id}",'dashboard'=>"/app/dashboard/{$resource->id}",'automation'=>"/app/automations/{$resource->id}",'report'=>"/app/reports/{$resource->id}",'webhook'=>"/app/developer/webhooks/{$resource->id}",'firmware'=>"/app/developer/firmware"}];
    }

    private function related($query, Collection $rows) { return $query->where(function ($outer) use ($rows) { foreach ($this->policies->types() as $type) $outer->orWhere(fn ($part) => $part->where('resource_type', $type)->whereIn('resource_id', $rows->where('resource_type', $type)->pluck('resource_id'))); }); }
    private function selectedTypes(array $filters): array { return isset($filters['resource_type']) ? [$filters['resource_type']] : $this->policies->types(); }
    private function authorize(User $admin, array $filters): void { abort_unless($admin->isPlatformAdmin(), 403); if (isset($filters['resource_type']) && !in_array($filters['resource_type'], $this->policies->types(), true)) throw ValidationException::withMessages(['resource_type' => ['Unsupported disabled resource type.']]); }
}
