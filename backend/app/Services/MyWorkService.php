<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceRegistry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MyWorkService
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
        foreach (array_keys(self::RESOURCES) as $type) $this->resources->definition($type);
    }

    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        return $this->query($user, $filters)->paginate($filters['per_page'] ?? 25)->through(fn ($row) => $this->dto($row));
    }

    public function count(User $user, array $filters = []): int
    {
        return $this->query($user, $filters)->count();
    }

    public function preview(User $user, int $limit = 5): array
    {
        $limit = min(5, max(1, $limit));
        return $this->query($user, [])->limit($limit)->get()->map(fn ($row) => $this->dto($row))->all();
    }

    private function query(User $user, array $filters): Builder
    {
        abort_unless($user->status === 'active' && $user->organization_id, 403);
        $type = $filters['resource_type'] ?? null;
        if ($type !== null && ! isset(self::RESOURCES[$type])) throw ValidationException::withMessages(['resource_type' => ['Unsupported resource type.']]);

        $queries = [];
        foreach (self::RESOURCES as $key => $definition) if ($type === null || $type === $key) $queries[] = $this->resourceQuery($user, $key, $definition);
        $union = array_shift($queries);
        foreach ($queries as $query) $union->unionAll($query);

        return DB::query()->fromSub($union, 'work')
            ->when($filters['lifecycle'] ?? null, fn (Builder $q, string $value) => $q->where('lifecycle', $value))
            ->when($filters['relationship'] ?? null, function (Builder $q, string $value) {
                $column = match ($value) { 'creator' => 'is_creator', 'contributor' => 'is_contributor', 'draft' => 'has_draft' };
                $q->where($column, 1);
            })
            ->when($filters['q'] ?? null, function (Builder $query, string $value) {
                $escaped = '%'.$this->escapeLike(mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? '')).'%';
                $query->where(fn (Builder $q) => $q->whereRaw("LOWER(label) LIKE ? ESCAPE '\\'", [$escaped])->orWhereRaw("LOWER(resource_id) LIKE ? ESCAPE '\\'", [$escaped]));
            })
            ->orderByDesc('relevant_at')->orderBy('resource_type')->orderBy('resource_id');
    }

    private function resourceQuery(User $user, string $type, array $definition): Builder
    {
        [$table, $creator, $label, $destination] = $definition;
        $query = DB::table("{$table} as resources")
            ->join('organizations as organizations', 'organizations.id', '=', 'resources.organization_id')
            ->leftJoin('resource_lifecycle_states as lifecycle', fn ($join) => $join->on('lifecycle.resource_id', '=', 'resources.id')->where('lifecycle.resource_type', $type))
            ->where('resources.organization_id', $user->organization_id)
            ->when(in_array($type, ['report', 'webhook'], true), fn (Builder $q) => $q->whereNull('resources.deleted_at'))
            ->when($type === 'dashboard', fn (Builder $q) => $q->where('resources.scope_type', 'personal'));

        if ($type === 'device') {
            $query->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('device_access_assignments as access')->whereColumn('access.device_id', 'resources.id')->where('access.user_id', $user->id)->whereIn('access.access_level', ['viewer', 'full_access']));
        } elseif ($type === 'dashboard') {
            $query->where(fn (Builder $q) => $q->where('resources.owner_user_id', $user->id)->orWhereExists(fn (Builder $grant) => $grant->selectRaw('1')->from('resource_collaborators as access')->whereColumn('access.resource_id', 'resources.id')->where(['access.resource_type' => $type, 'access.user_id' => $user->id])->whereIn('access.permission', ['view', 'edit'])));
        } else {
            $query->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('resource_collaborators as access')->whereColumn('access.resource_id', 'resources.id')->where(['access.resource_type' => $type, 'access.user_id' => $user->id])->whereIn('access.permission', ['view', 'edit']));
        }

        $creatorSql = "CASE WHEN resources.{$creator} = ? THEN 1 ELSE 0 END";
        $contributorSql = "CASE WHEN EXISTS (SELECT 1 FROM resource_revisions rr WHERE rr.resource_type = ? AND rr.resource_id = resources.id AND rr.created_by = ?) THEN 1 ELSE 0 END";
        $draftSql = "CASE WHEN EXISTS (SELECT 1 FROM resource_drafts rd WHERE rd.resource_type = ? AND rd.resource_id = resources.id AND rd.user_id = ?) THEN 1 ELSE 0 END";
        $relevantSql = "CASE WHEN resources.{$creator} = ? THEN resources.created_at ELSE NULL END";
        $query->where(function (Builder $q) use ($user, $type, $creator) {
            $q->where("resources.{$creator}", $user->id)
                ->orWhereExists(fn (Builder $r) => $r->selectRaw('1')->from('resource_revisions as rr')->whereColumn('rr.resource_id', 'resources.id')->where(['rr.resource_type' => $type, 'rr.created_by' => $user->id]))
                ->orWhereExists(fn (Builder $d) => $d->selectRaw('1')->from('resource_drafts as rd')->whereColumn('rd.resource_id', 'resources.id')->where(['rd.resource_type' => $type, 'rd.user_id' => $user->id]));
        });

        return $query->selectRaw("? as resource_type, CAST(resources.id AS TEXT) as resource_id, resources.{$label} as label, resources.organization_id, organizations.name as organization_name, COALESCE(lifecycle.state, 'active') as lifecycle, {$creatorSql} as is_creator, {$contributorSql} as is_contributor, {$draftSql} as has_draft, COALESCE((SELECT MAX(rr2.created_at) FROM resource_revisions rr2 WHERE rr2.resource_type = ? AND rr2.resource_id = resources.id AND rr2.created_by = ?), (SELECT MAX(rd2.updated_at) FROM resource_drafts rd2 WHERE rd2.resource_type = ? AND rd2.resource_id = resources.id AND rd2.user_id = ?), {$relevantSql}) as relevant_at, (? || resources.id) as destination", [$type, $user->id, $type, $user->id, $type, $user->id, $type, $user->id, $type, $user->id, $user->id, $destination]);
    }

    private function dto(object $row): array
    {
        $keys = [];
        if ($row->is_creator) $keys[] = 'creator';
        if ($row->is_contributor) $keys[] = 'contributor';
        if ($row->has_draft) $keys[] = 'draft';
        return ['resourceType' => $row->resource_type, 'resourceId' => (string) $row->resource_id, 'label' => $row->label, 'organization' => ['id' => (string) $row->organization_id, 'name' => $row->organization_name], 'lifecycle' => $row->lifecycle, 'relationshipKeys' => $keys, 'relevantAt' => $row->relevant_at, 'destination' => $row->destination];
    }

    private function escapeLike(string $value): string { return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value); }
}
