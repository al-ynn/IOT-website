<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceRegistry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CrossDomainSearchService
{
    /**
     * Explicit safe corpus. Configuration, secrets, runtime state and raw snapshots
     * are deliberately absent. Child definitions always resolve to their parent.
     */
    private const RESOURCES = [
        'device' => ['devices', 'name', ['external_id', 'type', 'protocol'], '/app/devices/'],
        'device_template' => ['device_templates', 'name', ['description', 'device_type', 'protocol'], '/app/developer/templates/'],
        'dashboard' => ['dashboards', 'name', ['description'], '/app/dashboard/'],
        'automation' => ['automations', 'name', ['description'], '/app/automations/'],
        'report' => ['reports', 'name', ['description', 'report_type'], '/app/reports/'],
        'location' => ['locations', 'name', ['description'], '/app/locations/'],
        'firmware' => ['firmware_artifacts', 'name', ['version', 'description', 'device_type', 'protocol'], '/app/developer/firmware'],
        'webhook' => ['webhooks', 'name', [], '/app/developer/webhooks/'],
    ];

    public function __construct(private CollaborationResourceRegistry $resources)
    {
        foreach (array_keys(self::RESOURCES) as $type) {
            $this->resources->definition($type);
        }
    }

    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        abort_unless($user->status === 'active' && $user->organization_id, 403);
        $type = $filters['resource_type'] ?? null;
        if ($type !== null && ! isset(self::RESOURCES[$type])) {
            throw ValidationException::withMessages(['resource_type' => ['Unsupported resource type.']]);
        }

        $term = mb_strtolower(preg_replace('/\s+/u', ' ', trim($filters['q'])) ?? '');
        $like = '%'.$this->escapeLike($term).'%';
        $prefix = $this->escapeLike($term).'%';
        $queries = [];

        foreach (self::RESOURCES as $key => $definition) {
            if ($type === null || $type === $key) {
                $queries[] = $this->privateResourceQuery($user, $key, $definition, $term, $like, $prefix);
            }
        }
        if ($type === null || $type === 'dashboard') {
            $queries[] = $this->publishedDashboardQuery($user, $term, $like, $prefix);
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'search_results')
            ->when($filters['lifecycle'] ?? null, fn (Builder $query, string $state) => $query->where('lifecycle', $state))
            ->orderByDesc('rank')
            ->orderByRaw('LOWER(label)')
            ->orderBy('resource_type')
            ->orderBy('resource_id')
            ->paginate($filters['per_page'] ?? 25)
            ->through(fn ($row) => [
                'resultKind' => 'resource',
                'resourceType' => $row->resource_type,
                'resourceId' => (string) $row->resource_id,
                'label' => $row->label,
                'lifecycle' => $row->lifecycle,
                'accessMode' => $row->access_mode,
                'matchKind' => $row->match_kind,
                'matchedFieldLabel' => $row->matched_field_label,
                'matchedContext' => mb_substr((string) $row->matched_context, 0, 220),
                'destination' => $row->destination,
            ]);
    }

    private function privateResourceQuery(User $user, string $type, array $definition, string $term, string $like, string $prefix): Builder
    {
        [$table, $label, $metadata, $destination] = $definition;
        $query = DB::table("{$table} as resources")
            ->leftJoin('resource_lifecycle_states as lifecycle', fn ($join) => $join->on('lifecycle.resource_id', '=', 'resources.id')->where('lifecycle.resource_type', $type))
            ->where('resources.organization_id', $user->organization_id);

        if ($type === 'device') {
            $query->join('device_access_assignments as grants', 'grants.device_id', '=', 'resources.id')
                ->where('grants.user_id', $user->id)
                ->whereIn('grants.access_level', ['viewer', 'full_access'])
                ->leftJoin('resource_revision_states as presentation', function ($join) use ($user) {
                    $join->on('presentation.resource_id', '=', 'resources.id')
                        ->where('presentation.resource_type', 'device')
                        ->where('presentation.user_id', $user->id);
                })->leftJoin('resource_revisions as accepted', 'accepted.id', '=', 'presentation.accepted_revision_id');
            $access = 'grants.access_level';
        } elseif ($type === 'dashboard') {
            $query->leftJoin('resource_collaborators as grants', function ($join) use ($type, $user) {
                $join->on('grants.resource_id', '=', 'resources.id')
                    ->where('grants.resource_type', $type)
                    ->where('grants.user_id', $user->id);
            })->where('resources.scope_type', 'personal')
                ->where(function (Builder $access) use ($user) {
                    $access->where('resources.owner_user_id', $user->id)
                        ->orWhereIn('grants.permission', ['view', 'edit']);
                });
            $access = "CASE WHEN resources.owner_user_id = {$user->id} THEN 'edit' ELSE grants.permission END";
        } else {
            $query->join('resource_collaborators as grants', function ($join) use ($type) {
                $join->on('grants.resource_id', '=', 'resources.id')->where('grants.resource_type', $type);
            })->where('grants.user_id', $user->id)
                ->whereIn('grants.permission', ['view', 'edit']);
            $access = 'grants.permission';
        }

        [$childMatch, $childBindings, $childContext, $childContextBindings, $childLabel] = $this->childSearch($type, $like);
        $searchable = array_merge([$label], $metadata);
        $query->where(function (Builder $where) use ($searchable, $like, $childMatch, $childBindings) {
            $where->whereRaw("LOWER(CAST(resources.id AS TEXT)) LIKE ? ESCAPE '\\'", [$like]);
            foreach ($searchable as $field) {
                $where->orWhereRaw("LOWER(COALESCE(resources.{$field}, '')) LIKE ? ESCAPE '\\'", [$like]);
            }
            if ($childMatch) {
                $where->orWhereRaw($childMatch, $childBindings);
            }
        });

        $metadataSql = $metadata
            ? implode(' OR ', array_map(fn ($field) => "LOWER(COALESCE(resources.{$field}, '')) LIKE ? ESCAPE '\\'", $metadata))
            : '0 = 1';
        $metadataBindings = array_fill(0, count($metadata), $like);
        $metadataContext = $metadata
            ? 'COALESCE('.implode(', ', array_map(fn ($field) => "resources.{$field}", $metadata)).", '')"
            : "''";

        $rankSql = "CASE WHEN LOWER(CAST(resources.id AS TEXT)) = ? THEN 100
            WHEN LOWER(resources.{$label}) = ? THEN 90
            WHEN LOWER(resources.{$label}) LIKE ? ESCAPE '\\' THEN 80
            WHEN LOWER(resources.{$label}) LIKE ? ESCAPE '\\' THEN 70
            WHEN ({$metadataSql}) THEN 50";
        $rankBindings = array_merge([$term, $term, $prefix, $like], $metadataBindings);
        if ($childMatch) {
            $rankSql .= " WHEN ({$childMatch}) THEN 40";
            $rankBindings = array_merge($rankBindings, $childBindings);
        }
        $rankSql .= ' ELSE 1 END';

        $matchSql = "CASE WHEN LOWER(CAST(resources.id AS TEXT)) = ? THEN 'exact_id'
            WHEN LOWER(resources.{$label}) = ? THEN 'exact_label'
            WHEN LOWER(resources.{$label}) LIKE ? ESCAPE '\\' THEN 'label_prefix'
            WHEN LOWER(resources.{$label}) LIKE ? ESCAPE '\\' THEN 'label_contains'
            WHEN ({$metadataSql}) THEN 'metadata'";
        $matchBindings = array_merge([$term, $term, $prefix, $like], $metadataBindings);
        if ($childMatch) {
            $matchSql .= " WHEN ({$childMatch}) THEN 'child_metadata'";
            $matchBindings = array_merge($matchBindings, $childBindings);
        }
        $matchSql .= " ELSE 'metadata' END";

        $fieldSql = "CASE WHEN ({$metadataSql}) THEN 'Metadata'";
        $fieldBindings = $metadataBindings;
        if ($childMatch) {
            $fieldSql .= " WHEN ({$childMatch}) THEN ?";
            $fieldBindings = array_merge($fieldBindings, $childBindings, [$childLabel]);
        }
        $fieldSql .= " ELSE 'Name or ID' END";

        $contextSql = "CASE WHEN ({$metadataSql}) THEN {$metadataContext}";
        $contextBindings = $metadataBindings;
        if ($childMatch) {
            $contextSql .= " WHEN ({$childMatch}) THEN ({$childContext})";
            $contextBindings = array_merge($contextBindings, $childBindings, $childContextBindings);
        }
        $contextSql .= " ELSE resources.{$label} END";

        return $query->selectRaw(
            "? as resource_type, CAST(resources.id AS TEXT) as resource_id, resources.{$label} as label,
             COALESCE(lifecycle.state, 'active') as lifecycle, {$access} as access_mode,
             {$rankSql} as rank, {$matchSql} as match_kind,
             {$fieldSql} as matched_field_label, {$contextSql} as matched_context,
             (? || CAST(resources.id AS TEXT)) as destination",
            array_merge(
                [$type],
                $rankBindings,
                $matchBindings,
                $fieldBindings,
                $contextBindings,
                [$destination],
            )
        );
    }

    /** @return array{?string,array,?string,array,string} */
    private function childSearch(string $type, string $like): array
    {
        if ($type === 'device') {
            $parameter = "EXISTS (SELECT 1 FROM device_parameters child WHERE child.device_id = resources.id AND
                (LOWER(COALESCE(child.name, '')) LIKE ? ESCAPE '\\' OR LOWER(COALESCE(child.key, '')) LIKE ? ESCAPE '\\'
                OR LOWER(COALESCE(child.description, '')) LIKE ? ESCAPE '\\' OR LOWER(COALESCE(child.unit, '')) LIKE ? ESCAPE '\\'))";
            $parameterBindings = array_fill(0, 4, $like);
            [$widget, $widgetBindings, $widgetContext, $widgetContextBindings] = $this->jsonArraySearch('accepted.snapshot', 'dashboard.widgets', ['title', 'type'], $like);
            $match = "({$parameter}) OR ({$widget})";
            $context = "COALESCE((SELECT child.name FROM device_parameters child WHERE child.device_id = resources.id AND
                (LOWER(COALESCE(child.name, '')) LIKE ? ESCAPE '\\' OR LOWER(COALESCE(child.key, '')) LIKE ? ESCAPE '\\'
                OR LOWER(COALESCE(child.description, '')) LIKE ? ESCAPE '\\' OR LOWER(COALESCE(child.unit, '')) LIKE ? ESCAPE '\\')
                ORDER BY child.id LIMIT 1), ({$widgetContext}), '')";
            return [$match, array_merge($parameterBindings, $widgetBindings), $context, array_merge($parameterBindings, $widgetContextBindings), 'Parameter or accepted Dashboard widget'];
        }
        if ($type === 'device_template') {
            return $this->relationalChildSearch('device_template_parameters', 'device_template_id', ['name', 'key', 'description', 'unit'], 'Template parameter', $like);
        }
        if ($type === 'dashboard') {
            return $this->relationalChildSearch('dashboard_widgets', 'dashboard_id', ['title', 'widget_type'], 'Dashboard widget', $like);
        }

        return [null, [], null, [], ''];
    }

    /** @return array{string,array,string,array,string} */
    private function relationalChildSearch(string $table, string $foreignKey, array $fields, string $label, string $like): array
    {
        $parts = array_map(fn ($field) => "LOWER(COALESCE(child.{$field}, '')) LIKE ? ESCAPE '\\'", $fields);
        $predicate = implode(' OR ', $parts);
        $bindings = array_fill(0, count($fields), $like);
        $match = "EXISTS (SELECT 1 FROM {$table} child WHERE child.{$foreignKey} = resources.id AND ({$predicate}))";
        $context = "(SELECT child.{$fields[0]} FROM {$table} child WHERE child.{$foreignKey} = resources.id AND ({$predicate}) ORDER BY child.id LIMIT 1)";

        return [$match, $bindings, $context, $bindings, $label];
    }

    private function publishedDashboardQuery(User $user, string $term, string $like, string $prefix): Builder
    {
        $driver = DB::connection()->getDriverName();
        $name = $this->jsonText('revision.snapshot', 'metadata.name', $driver);
        $description = $this->jsonText('revision.snapshot', 'metadata.description', $driver);
        [$widget, $widgetBindings, $widgetContext, $widgetContextBindings] = $this->jsonArraySearch('revision.snapshot', 'dashboard.widgets', ['title', 'type'], $like);

        $query = DB::table('resource_publication_states as publication')
            ->join('resource_publication_versions as version', 'version.id', '=', 'publication.current_publication_version_id')
            ->join('resource_revisions as revision', 'revision.id', '=', 'version.resource_revision_id')
            ->join('dashboards as resources', 'resources.id', '=', 'publication.resource_id')
            ->leftJoin('resource_lifecycle_states as lifecycle', fn ($join) => $join->on('lifecycle.resource_id', '=', 'resources.id')->where('lifecycle.resource_type', 'dashboard'))
            ->where('publication.resource_type', 'dashboard')
            ->where('resources.organization_id', $user->organization_id)
            ->where('resources.scope_type', 'personal')
            ->whereRaw("COALESCE(lifecycle.state, 'active') = 'active'")
            ->whereNotExists(fn (Builder $grant) => $grant->selectRaw('1')->from('resource_collaborators')
                ->whereColumn('resource_collaborators.resource_id', 'resources.id')
                ->where('resource_collaborators.resource_type', 'dashboard')
                ->where('resource_collaborators.user_id', $user->id))
            ->where(function (Builder $where) use ($like, $name, $description, $widget, $widgetBindings) {
                $where->whereRaw("LOWER(CAST(resources.id AS TEXT)) LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("LOWER(COALESCE({$name}, '')) LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("LOWER(COALESCE({$description}, '')) LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw($widget, $widgetBindings);
            });

        $rank = "CASE WHEN LOWER(CAST(resources.id AS TEXT)) = ? THEN 100
            WHEN LOWER(COALESCE({$name}, '')) = ? THEN 90
            WHEN LOWER(COALESCE({$name}, '')) LIKE ? ESCAPE '\\' THEN 80
            WHEN LOWER(COALESCE({$name}, '')) LIKE ? ESCAPE '\\' THEN 70
            WHEN LOWER(COALESCE({$description}, '')) LIKE ? ESCAPE '\\' THEN 50
            WHEN ({$widget}) THEN 40 ELSE 1 END";
        $match = "CASE WHEN LOWER(CAST(resources.id AS TEXT)) = ? THEN 'exact_id'
            WHEN LOWER(COALESCE({$name}, '')) = ? THEN 'exact_label'
            WHEN LOWER(COALESCE({$name}, '')) LIKE ? ESCAPE '\\' THEN 'label_prefix'
            WHEN LOWER(COALESCE({$name}, '')) LIKE ? ESCAPE '\\' THEN 'label_contains'
            WHEN LOWER(COALESCE({$description}, '')) LIKE ? ESCAPE '\\' THEN 'metadata'
            WHEN ({$widget}) THEN 'child_metadata' ELSE 'metadata' END";
        $field = "CASE WHEN LOWER(COALESCE({$description}, '')) LIKE ? ESCAPE '\\' THEN 'Metadata'
            WHEN ({$widget}) THEN 'Dashboard widget' ELSE 'Name or ID' END";
        $context = "CASE WHEN LOWER(COALESCE({$description}, '')) LIKE ? ESCAPE '\\' THEN {$description}
            WHEN ({$widget}) THEN ({$widgetContext}) ELSE {$name} END";

        return $query->selectRaw(
            "'dashboard' as resource_type, CAST(resources.id AS TEXT) as resource_id, {$name} as label,
             'active' as lifecycle, 'published' as access_mode, {$rank} as rank, {$match} as match_kind,
             {$field} as matched_field_label, {$context} as matched_context,
             ('/app/dashboard/published/' || CAST(resources.id AS TEXT)) as destination",
            array_merge(
                [$term, $term, $prefix, $like, $like], $widgetBindings,
                [$term, $term, $prefix, $like, $like], $widgetBindings,
                [$like], $widgetBindings,
                [$like], $widgetBindings, $widgetContextBindings,
            )
        );
    }

    /** @return array{string,array,string,array} */
    private function jsonArraySearch(string $column, string $path, array $fields, string $like): array
    {
        $driver = DB::connection()->getDriverName();
        $bindings = array_fill(0, count($fields), $like);
        if ($driver === 'pgsql') {
            $segments = explode('.', $path);
            $json = $column;
            foreach ($segments as $segment) {
                $json .= "->'{$segment}'";
            }
            $parts = array_map(fn ($field) => "LOWER(COALESCE(child.value->>'{$field}', '')) LIKE ? ESCAPE '\\'", $fields);
            $match = "EXISTS (SELECT 1 FROM jsonb_array_elements(COALESCE({$json}, '[]'::jsonb)) child(value) WHERE ".implode(' OR ', $parts).')';
            $context = "(SELECT COALESCE(child.value->>'{$fields[0]}', child.value->>'{$fields[1]}', '') FROM jsonb_array_elements(COALESCE({$json}, '[]'::jsonb)) child(value) WHERE ".implode(' OR ', $parts).' LIMIT 1)';
            return [$match, $bindings, $context, $bindings];
        }
        if ($driver === 'mysql') {
            $columns = implode(', ', array_map(fn ($field) => "{$field} VARCHAR(255) PATH '$.{$field}'", $fields));
            $parts = array_map(fn ($field) => "LOWER(COALESCE(child.{$field}, '')) LIKE ? ESCAPE '\\\\'", $fields);
            $source = "JSON_TABLE(COALESCE({$column}, JSON_OBJECT()), '$.".str_replace('.', '.', $path)."[*]' COLUMNS ({$columns})) child";
            $match = 'EXISTS (SELECT 1 FROM '.$source.' WHERE '.implode(' OR ', $parts).')';
            $context = "(SELECT COALESCE(child.{$fields[0]}, child.{$fields[1]}, '') FROM {$source} WHERE ".implode(' OR ', $parts).' LIMIT 1)';
            return [$match, $bindings, $context, $bindings];
        }

        $parts = array_map(fn ($field) => "LOWER(COALESCE(json_extract(child.value, '$.{$field}'), '')) LIKE ? ESCAPE '\\'", $fields);
        $source = "json_each({$column}, '$.".str_replace('.', '.', $path)."') child";
        $match = 'EXISTS (SELECT 1 FROM '.$source.' WHERE '.implode(' OR ', $parts).')';
        $context = "(SELECT COALESCE(json_extract(child.value, '$.{$fields[0]}'), json_extract(child.value, '$.{$fields[1]}'), '') FROM {$source} WHERE ".implode(' OR ', $parts).' LIMIT 1)';
        return [$match, $bindings, $context, $bindings];
    }

    private function jsonText(string $column, string $path, string $driver): string
    {
        if ($driver === 'pgsql') {
            $segments = explode('.', $path);
            $last = array_pop($segments);
            $json = $column;
            foreach ($segments as $segment) {
                $json .= "->'{$segment}'";
            }
            return "{$json}->>'{$last}'";
        }
        if ($driver === 'mysql') {
            return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$path}'))";
        }
        return "json_extract({$column}, '$.{$path}')";
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
