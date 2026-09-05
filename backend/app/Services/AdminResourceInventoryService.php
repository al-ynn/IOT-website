<?php

namespace App\Services;

use App\Inventory\ResourceInventoryAdapter;
use App\Inventory\ResourceInventoryAdapterRegistry;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminResourceInventoryService
{
    public function __construct(private ResourceInventoryAdapterRegistry $registry, private AdminResourceViewService $views) {}

    public function types(): array
    {
        return array_map(fn (ResourceInventoryAdapter $adapter) => [
            'key' => $adapter->type,
            'label' => $adapter->label,
        ], $this->registry->all());
    }

    public function index(User $admin, array $filters): LengthAwarePaginator
    {
        abort_unless($admin->isPlatformAdmin(), 403);
        $filters['organization_id'] = $admin->organization_id;
        $adapters = isset($filters['resource_type'])
            ? [$this->registry->get($filters['resource_type'])]
            : $this->registry->all();

        $queries = array_map(fn (ResourceInventoryAdapter $adapter) => $this->query($adapter, $filters), $adapters);
        $union = array_shift($queries);
        foreach ($queries as $query) $union->unionAll($query);

        $query = DB::query()->fromSub($union, 'resource_inventory')->select('*');
        match ($filters['sort'] ?? 'label') {
            'type' => $query->orderBy('resource_type')->orderBy('resource_label')->orderBy('resource_id'),
            default => $query->orderBy('resource_label')->orderBy('resource_type')->orderBy('resource_id'),
        };

        $page = $query->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
        $page->setCollection($this->views->decorate($admin,$page->getCollection()->map(fn (object $row) => $this->row($row))));

        return $page;
    }

    public function show(User $admin, string $type, string $id): array
    {
        abort_unless($admin->isPlatformAdmin(), 403);
        $adapter = $this->registry->get($type);
        $record = $this->query($adapter, ['organization_id' => $admin->organization_id])->where('resource.id', $id)->first();
        abort_unless($record, 404);

        return $this->views->decorate($admin, collect([$this->row($record)]))->first();
    }
    private function query(ResourceInventoryAdapter $adapter, array $filters)
    {
        $resource = 'resource';
        $query = DB::table($adapter->table.' as '.$resource)
            ->leftJoin('organizations as organization', 'organization.id', '=', $resource.'.organization_id')
            ->leftJoin('users as creator', 'creator.id', '=', $resource.'.'.$adapter->creatorColumn)
            ->leftJoin('resource_lifecycle_states as lifecycle', function ($join) use ($adapter, $resource) {
                $join->on('lifecycle.resource_id', '=', $resource.'.id')
                    ->where('lifecycle.resource_type', '=', $adapter->type);
            })
            ->selectRaw('? as resource_type, resource.id as resource_id, resource.name as resource_label, resource.organization_id, organization.name as organization_name, COALESCE(lifecycle.state, ?) as lifecycle_state, resource.created_at, creator.id as creator_id, creator.name as creator_name, creator.status as creator_status', [$adapter->type, 'active']);

        if ($adapter->softDeletes) $query->whereNull('resource.deleted_at');
        if (isset($filters['organization_id'])) $query->where('resource.organization_id', $filters['organization_id']);
        if (isset($filters['lifecycle'])) {
            $filters['lifecycle'] === 'active'
                ? $query->where(fn ($nested) => $nested->whereNull('lifecycle.state')->orWhere('lifecycle.state', 'active'))
                : $query->where('lifecycle.state', $filters['lifecycle']);
        }
        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($nested) use ($term) {
                $nested->where('resource.name', 'like', '%'.$term.'%')
                    ->orWhere('organization.name', 'like', '%'.$term.'%')
                    ->orWhereRaw($this->idCast().' LIKE ?', ['%'.$term.'%']);
            });
        }

        return $query;
    }

    private function row(object $row): array
    {
        $adapter = $this->registry->get($row->resource_type);
        $lifecycle = $row->lifecycle_state ?: 'active';

        return [
            'resourceType' => $adapter->type,
            'resourceTypeLabel' => $adapter->label,
            'resourceId' => (string) $row->resource_id,
            'label' => $row->resource_label,
            'organization' => $row->organization_id ? ['id' => (string) $row->organization_id, 'name' => $row->organization_name] : null,
            'lifecycle' => $lifecycle,
            'createdAt' => $row->created_at ? \Carbon\CarbonImmutable::parse($row->created_at)->toISOString() : null,
            'creator' => $row->creator_id ? ['id' => (string) $row->creator_id, 'name' => $row->creator_name, 'inactive' => $row->creator_status !== 'active'] : null,
            'adminDestination' => $adapter->destination((string) $row->resource_id),
            'capabilities' => [
                'canOpen' => true,
                'canDisable' => $lifecycle === 'active',
                'canRestore' => in_array($lifecycle, ['disabled', 'archived'], true),
            ],
        ];
    }

    private function idCast(): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? 'CAST(resource.id AS CHAR)'
            : 'CAST(resource.id AS TEXT)';
    }
}
