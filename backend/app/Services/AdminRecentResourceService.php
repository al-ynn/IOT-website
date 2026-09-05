<?php

namespace App\Services;

use App\Inventory\ResourceInventoryAdapter;
use App\Inventory\ResourceInventoryAdapterRegistry;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AdminRecentResourceService
{
    public function __construct(private ResourceInventoryAdapterRegistry $registry, private MeaningfulUpdatePolicyRegistry $meaningful, private AdminResourceViewService $views) {}

    public function created(User $admin, array $filters): LengthAwarePaginator
    {
        abort_unless($admin->isPlatformAdmin(), 403);
        $filters['organization_id'] = $admin->organization_id;
        $adapters = isset($filters['resource_type']) ? [$this->registry->get($filters['resource_type'])] : $this->registry->all();
        $queries = array_map(fn (ResourceInventoryAdapter $adapter) => $this->createdInventoryQuery($adapter, $filters), $adapters);
        $union = array_shift($queries);
        foreach ($queries as $next) $union->unionAll($next);

        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $page = DB::query()->fromSub($union, 'recently_created_resources')->select('*')
            ->orderBy('occurred_at', $direction)->orderBy('resource_type')->orderBy('resource_id')
            ->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
        $page->setCollection($this->views->decorate($admin,$page->getCollection()->map(fn (object $row) => $this->createdItem($row))));

        return $page;
    }

    private function createdInventoryQuery(ResourceInventoryAdapter $adapter, array $filters)
    {
        $query = DB::table($adapter->table.' as resource')
            ->leftJoin('organizations as organization', 'organization.id', '=', 'resource.organization_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'resource.'.$adapter->creatorColumn)
            ->leftJoin('resource_lifecycle_states as lifecycle', function ($join) use ($adapter) {
                $join->on('lifecycle.resource_id', '=', 'resource.id')->where('lifecycle.resource_type', '=', $adapter->type);
            })
            ->selectRaw('? as resource_type, resource.id as resource_id, resource.name as resource_label, resource.organization_id, organization.name as organization_name, COALESCE(lifecycle.state, ?) as lifecycle_state, resource.created_at as occurred_at, creator.id as actor_id, creator.name as actor_name, creator.status as actor_status', [$adapter->type, 'active'])
            ->whereNotNull('resource.created_at');
        if ($adapter->softDeletes) $query->whereNull('resource.deleted_at');
        if (isset($filters['organization_id'])) $query->where('resource.organization_id', $filters['organization_id']);
        if (isset($filters['lifecycle'])) {
            $filters['lifecycle'] === 'active'
                ? $query->where(fn ($nested) => $nested->whereNull('lifecycle.state')->orWhere('lifecycle.state', 'active'))
                : $query->where('lifecycle.state', $filters['lifecycle']);
        }
        if (($window = $filters['window'] ?? 'all') !== 'all') {
            $hours = ['24h' => 24, '7d' => 168, '30d' => 720, '90d' => 2160][$window];
            $query->where('resource.created_at', '>=', now()->subHours($hours));
        }
        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($nested) use ($term) {
                $nested->where('resource.name', 'like', '%'.$term.'%')->orWhere('organization.name', 'like', '%'.$term.'%');
            });
        }
        return $query;
    }

    private function createdItem(object $row): array
    {
        $adapter = $this->registry->get($row->resource_type);
        return [
            'resourceType' => $adapter->type,
            'resourceTypeLabel' => $adapter->label,
            'resourceId' => (string) $row->resource_id,
            'resourceLabel' => $row->resource_label,
            'organization' => $row->organization_id ? ['id'=>(string)$row->organization_id, 'name'=>$row->organization_name] : null,
            'lifecycle' => $row->lifecycle_state ?: 'active',
            'actor' => $row->actor_id ? ['id'=>(string)$row->actor_id, 'name'=>$row->actor_name, 'inactive'=>$row->actor_status !== 'active'] : null,
            'occurredAt' => \Carbon\CarbonImmutable::parse($row->occurred_at)->toISOString(),
            'revision' => null,
            'resourceLink' => $adapter->destination((string) $row->resource_id),
        ];
    }
    public function updated(User $admin, array $filters): LengthAwarePaginator
    {
        abort_unless($admin->isPlatformAdmin(), 403);
        $filters['organization_id'] = $admin->organization_id;
        $adapters = isset($filters['resource_type']) ? [$this->registry->get($filters['resource_type'])] : $this->registry->all();
        $queries = array_map(fn (ResourceInventoryAdapter $adapter) => $this->updatedInventoryQuery($adapter, $filters), $adapters);
        $union = array_shift($queries);
        foreach ($queries as $next) $union->unionAll($next);

        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $page = DB::query()->fromSub($union, 'recently_updated_resources')->select('*')
            ->orderBy('occurred_at', $direction)->orderBy('resource_type')->orderBy('resource_id')
            ->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
        $page->setCollection($this->views->decorate($admin,$page->getCollection()->map(fn (object $row) => $this->updatedItem($row))));

        return $page;
    }

    private function updatedInventoryQuery(ResourceInventoryAdapter $adapter, array $filters)
    {
        $eligible = DB::table('resource_revisions as candidate')
            ->selectRaw('MAX(candidate.revision_number)')
            ->where('candidate.resource_type', $adapter->type)
            ->whereColumn('candidate.resource_id', 'resource.id')
            ->where('candidate.revision_number', '>', 1)
            ->whereNotNull('candidate.parent_revision_id')
            ->whereNotNull('candidate.created_by')
            ->where(function ($query) use ($adapter) {
                foreach ($this->meaningful->meaningfulSections($adapter->type) as $section) {
                    $query->orWhereJsonContains('candidate.changed_sections', $section);
                }
            });

        $query = DB::table($adapter->table.' as resource')
            ->join('resource_revisions as revision', function ($join) use ($adapter, $eligible) {
                $join->on('revision.resource_id', '=', 'resource.id')
                    ->where('revision.resource_type', '=', $adapter->type)
                    ->where('revision.revision_number', '=', $eligible);
            })
            ->leftJoin('organizations as organization', 'organization.id', '=', 'resource.organization_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'revision.created_by')
            ->leftJoin('resource_lifecycle_states as lifecycle', function ($join) use ($adapter) {
                $join->on('lifecycle.resource_id', '=', 'resource.id')->where('lifecycle.resource_type', '=', $adapter->type);
            })
            ->selectRaw('? as resource_type, resource.id as resource_id, resource.name as resource_label, resource.organization_id, organization.name as organization_name, COALESCE(lifecycle.state, ?) as lifecycle_state, revision.created_at as occurred_at, revision.id as revision_id, revision.revision_number, revision.change_summary, revision.changed_sections, actor.id as actor_id, actor.name as actor_name, actor.status as actor_status', [$adapter->type, 'active']);
        if ($adapter->softDeletes) $query->whereNull('resource.deleted_at');
        if (isset($filters['organization_id'])) $query->where('resource.organization_id', $filters['organization_id']);
        if (isset($filters['lifecycle'])) {
            $filters['lifecycle'] === 'active'
                ? $query->where(fn ($nested) => $nested->whereNull('lifecycle.state')->orWhere('lifecycle.state', 'active'))
                : $query->where('lifecycle.state', $filters['lifecycle']);
        }
        if (($window = $filters['window'] ?? 'all') !== 'all') {
            $hours = ['24h'=>24, '7d'=>168, '30d'=>720, '90d'=>2160][$window];
            $query->where('revision.created_at', '>=', now()->subHours($hours));
        }
        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($nested) => $nested->where('resource.name', 'like', '%'.$term.'%')->orWhere('organization.name', 'like', '%'.$term.'%'));
        }
        return $query;
    }

    private function updatedItem(object $row): array
    {
        $adapter = $this->registry->get($row->resource_type);
        $sections = $this->meaningful->meaningfulSections($adapter->type);
        $changed = array_values(array_intersect($sections, json_decode((string) $row->changed_sections, true) ?: []));
        return [
            'resourceType'=>$adapter->type, 'resourceTypeLabel'=>$adapter->label,
            'resourceId'=>(string)$row->resource_id, 'resourceLabel'=>$row->resource_label,
            'organization'=>$row->organization_id ? ['id'=>(string)$row->organization_id,'name'=>$row->organization_name] : null,
            'lifecycle'=>$row->lifecycle_state ?: 'active',
            'actor'=>$row->actor_id ? ['id'=>(string)$row->actor_id,'name'=>$row->actor_name,'inactive'=>$row->actor_status !== 'active'] : null,
            'occurredAt'=>\Carbon\CarbonImmutable::parse($row->occurred_at)->toISOString(),
            'revision'=>['id'=>(string)$row->revision_id,'number'=>(int)$row->revision_number,'summary'=>$row->change_summary,'changedSections'=>$changed],
            'resourceLink'=>$adapter->destination((string)$row->resource_id),
        ];
    }
}
