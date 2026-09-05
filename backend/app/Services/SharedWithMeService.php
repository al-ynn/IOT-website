<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceRegistry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SharedWithMeService
{
    private const RESOURCES = [
        'device' => ['devices', 'created_by', 'name', '/app/devices/'],
        'device_template' => ['device_templates', 'created_by', 'name', '/app/developer/templates/'],
        'dashboard' => ['dashboards', 'created_by', 'name', '/app/dashboard/'],
        'automation' => ['automations', 'created_by', 'name', '/app/automations/'],
        'report' => ['reports', 'created_by', 'name', '/app/reports/'],
        'location' => ['locations', 'created_by', 'name', '/app/locations/'],
        'firmware' => ['firmware_artifacts', 'uploaded_by', 'name', '/app/developer/firmware'],
        'webhook' => ['webhooks', 'created_by', 'name', '/app/developer/webhooks/'],
    ];

    public function __construct(private CollaborationResourceRegistry $resources)
    {
        foreach (array_keys(self::RESOURCES) as $type) {
            $this->resources->definition($type);
        }
    }

    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        return $this->query($user, $filters)
            ->paginate($filters['per_page'] ?? 25)
            ->through(fn ($row) => $this->dto($row));
    }

    private function query(User $user, array $filters): Builder
    {
        abort_unless($user->status === 'active' && $user->organization_id, 403);
        $type = $filters['resource_type'] ?? null;
        if ($type !== null && ! isset(self::RESOURCES[$type])) {
            throw ValidationException::withMessages(['resource_type' => ['Unsupported resource type.']]);
        }

        $queries = [];
        if ($type === null || $type === 'device') {
            $queries[] = $this->deviceQuery($user);
        }
        foreach (self::RESOURCES as $key => $definition) {
            if ($key !== 'device' && ($type === null || $type === $key)) {
                $queries[] = $this->collaboratorQuery($user, $key, $definition);
            }
        }
        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        $query = DB::query()->fromSub($union, 'shared')
            ->when($filters['lifecycle'] ?? null, fn (Builder $q, string $value) => $q->where('lifecycle', $value))
            ->when($filters['permission'] ?? null, fn (Builder $q, string $value) => $q->where('access_key', $value))
            ->when($filters['q'] ?? null, function (Builder $query, string $value) {
                $escaped = '%'.$this->escapeLike(mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? '')).'%';
                $query->where(fn (Builder $q) => $q
                    ->whereRaw("LOWER(label) LIKE ? ESCAPE '\\'", [$escaped])
                    ->orWhereRaw("LOWER(resource_id) LIKE ? ESCAPE '\\'", [$escaped]));
            })
            ->orderByRaw('granted_at IS NULL')
            ->orderByDesc('granted_at')
            ->orderBy('resource_type')
            ->orderBy('resource_id');

        return $query;
    }

    private function dto(object $row): array
    {
        return [
            'resourceType' => $row->resource_type,
            'resourceId' => (string) $row->resource_id,
            'label' => $row->label,
            'organization' => ['id' => (string) $row->organization_id, 'name' => $row->organization_name],
            'lifecycle' => $row->lifecycle,
            'accessKey' => $row->access_key,
            'accessLabel' => match ($row->access_key) {
                'full_access' => 'Full Access', 'viewer' => 'Viewer', 'edit' => 'Edit', default => 'View',
            },
            'grantedAt' => $row->granted_at,
            'grantedBy' => $row->granted_by_id ? ['id' => (string) $row->granted_by_id, 'name' => $row->granted_by_name] : null,
            'destination' => $row->destination,
        ];
    }

    public function count(User $user): int
    {
        return $this->query($user, [])->count();
    }

    public function preview(User $user, int $limit = 5): array
    {
        return $this->query($user, [])->limit(min(5, max(1, $limit)))->get()
            ->map(fn ($row) => $this->dto($row))->all();
    }

    private function deviceQuery(User $user): Builder
    {
        return DB::table('device_access_assignments as grants')
            ->join('devices as resources', 'resources.id', '=', 'grants.device_id')
            ->join('organizations as organizations', 'organizations.id', '=', 'resources.organization_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'grants.assigned_by')
            ->leftJoin('resource_lifecycle_states as lifecycle', fn ($join) => $join->on('lifecycle.resource_id', '=', 'resources.id')->where('lifecycle.resource_type', 'device'))
            ->where('grants.user_id', $user->id)->where('resources.organization_id', $user->organization_id)
            ->whereIn('grants.access_level', ['viewer', 'full_access'])
            ->where(fn ($q) => $q->whereNull('resources.created_by')->orWhere('resources.created_by', '!=', $user->id))
            ->selectRaw("'device' as resource_type, CAST(resources.id AS TEXT) as resource_id, resources.name as label, resources.organization_id, organizations.name as organization_name, COALESCE(lifecycle.state, 'active') as lifecycle, grants.access_level as access_key, grants.created_at as granted_at, actors.id as granted_by_id, actors.name as granted_by_name, ('/app/devices/' || resources.id) as destination");
    }

    private function collaboratorQuery(User $user, string $type, array $definition): Builder
    {
        [$table, $creator, $label, $destination] = $definition;

        return DB::table('resource_collaborators as grants')
            ->join("{$table} as resources", 'resources.id', '=', 'grants.resource_id')
            ->join('organizations as organizations', 'organizations.id', '=', 'resources.organization_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'grants.granted_by')
            ->leftJoin('resource_lifecycle_states as lifecycle', fn ($join) => $join->on('lifecycle.resource_id', '=', 'resources.id')->where('lifecycle.resource_type', $type))
            ->where(['grants.user_id' => $user->id, 'grants.resource_type' => $type, 'resources.organization_id' => $user->organization_id])
            ->whereIn('grants.permission', ['view', 'edit'])
            ->when($type === 'dashboard', fn (Builder $query) => $query->where('resources.scope_type', 'personal'))
            ->where(fn ($q) => $q->whereNull("resources.{$creator}")->orWhere("resources.{$creator}", '!=', $user->id))
            ->selectRaw("? as resource_type, CAST(resources.id AS TEXT) as resource_id, resources.{$label} as label, resources.organization_id, organizations.name as organization_name, COALESCE(lifecycle.state, 'active') as lifecycle, grants.permission as access_key, grants.created_at as granted_at, actors.id as granted_by_id, actors.name as granted_by_name, (? || resources.id) as destination", [$type, $destination]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
