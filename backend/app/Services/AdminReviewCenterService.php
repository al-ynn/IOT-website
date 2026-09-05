<?php

namespace App\Services;

use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AdminReviewCenterService
{
    /** @deprecated Runtime composition is owned by ReviewDomainRegistry. */
    public const TYPES = [
        'device_template',
        'dashboard',
        'automation',
        'report',
        'webhook',
        'firmware',
    ];

    public const STATES = ['all_active', 'available', 'in_review', 'mine', 'reviewer_unavailable'];

    public function __construct(
        private ReviewOwnershipService $ownership,
        private ReviewDomainRegistry $domains,
    ) {}

    public function summary(User $admin, array $filters = []): array
    {
        $this->assertAdmin($admin);
        $filters['organization_id'] = $admin->organization_id;
        $base = $this->baseQuery($filters);

        return [
            'active' => (clone $base)->count(),
            ...$this->ownership->counts($base, $admin),
            'byResourceType' => collect($this->domains->types())
                ->mapWithKeys(fn (string $type): array => [
                    $type => (clone $base)->where('resource_type', $type)->count(),
                ])->all(),
        ];
    }

    public function list(User $admin, array $filters): LengthAwarePaginator
    {
        $this->assertAdmin($admin);
        $filters['organization_id'] = $admin->organization_id;
        $state = $filters['state'] ?? 'all_active';

        if (! in_array($state, self::STATES, true)) {
            throw ValidationException::withMessages(['state' => ['Unsupported review state.']]);
        }
        if (isset($filters['resource_type'])) {
            $this->domains->definition($filters['resource_type']);
        }

        $query = $this->baseQuery($filters)->with([
            'revision:id,resource_type,resource_id,revision_number',
            'submitter:id,name,status',
            'reviewer:id,name,status,platform_role',
        ]);
        $this->ownership->applyFilter($query, $state, $admin);

        ($filters['sort'] ?? 'oldest') === 'newest'
            ? $query->latest('submitted_at')->latest('id')
            : $query->oldest('submitted_at')->oldest('id');

        $page = $query->paginate((int) ($filters['per_page'] ?? 25));
        $rows = $page->getCollection();
        if ($rows->isEmpty()) {
            return $page;
        }

        $resources = $this->loadResources($rows);
        $pairs = $rows->map(fn ($submission): array => [
            $submission->resource_type,
            (string) $submission->resource_id,
        ]);
        $latest = $this->loadLatestRevisions($pairs);
        $publications = $this->loadPublicationStates($pairs);

        return $page->through(fn ($submission): array => $this->present(
            $submission,
            $resources[$submission->resource_type]->get((string) $submission->resource_id),
            $latest->get("{$submission->resource_type}:{$submission->resource_id}"),
            $publications->get("{$submission->resource_type}:{$submission->resource_id}"),
            $admin,
        ));
    }

    public function detail(User $admin, string $type, ResourcePublicationSubmission $submission): array
    {
        $this->assertAdmin($admin);
        $this->domains->definition($type);
        abort_unless($submission->resource_type === $type, 404);

        $definition = $this->domains->definition($type);
        abort_unless($definition['model']::query()->where('organization_id', $admin->organization_id)->whereKey($submission->resource_id)->exists(), 404);

        $submission->load([
            'revision:id,resource_type,resource_id,revision_number',
            'submitter:id,name,status',
            'reviewer:id,name,status,platform_role',
        ]);
        $rows = collect([$submission]);
        $resources = $this->loadResources($rows);
        $pairs = collect([[$type, (string) $submission->resource_id]]);

        return $this->present(
            $submission,
            $resources[$type]->get((string) $submission->resource_id),
            $this->loadLatestRevisions($pairs)->get("{$type}:{$submission->resource_id}"),
            $this->loadPublicationStates($pairs)->get("{$type}:{$submission->resource_id}"),
            $admin,
        );
    }

    private function baseQuery(array $filters): Builder
    {
        $types = isset($filters['resource_type'])
            ? [$filters['resource_type']]
            : $this->domains->types();
        $query = ResourcePublicationSubmission::query()
            ->whereIn('resource_type', $types)
            ->whereIn('status', ResourcePublicationSubmission::ACTIVE_STATUSES);

        foreach (['organization_id', 'search'] as $key) {
            if (! isset($filters[$key]) || trim((string) $filters[$key]) === '') {
                continue;
            }
            $value = $filters[$key];
            $query->where(function (Builder $outer) use ($types, $key, $value): void {
                foreach ($types as $type) {
                    $model = $this->domains->definition($type)['model'];
                    $resources = $model::query()->select('id');
                    $key === 'organization_id'
                        ? $resources->where('organization_id', $value)
                        : $resources->where('name', 'like', '%'.trim((string) $value).'%');
                    $outer->orWhere(fn (Builder $part) => $part
                        ->where('resource_type', $type)
                        ->whereIn('resource_id', $resources));
                }
            });
        }

        return $query;
    }

    private function loadResources(Collection $rows): array
    {
        $resources = [];
        foreach ($this->domains->definitions() as $type => $definition) {
            $ids = $rows->where('resource_type', $type)->pluck('resource_id');
            $resources[$type] = $definition['model']::query()
                ->whereIn('id', $ids)
                ->with('organization:id,name')
                ->get()
                ->keyBy(fn ($resource): string => (string) $resource->id);
        }

        return $resources;
    }

    private function loadLatestRevisions(Collection $pairs): Collection
    {
        return ResourceRevision::query()
            ->where(function (Builder $query) use ($pairs): void {
                foreach ($pairs as [$type, $id]) {
                    $query->orWhere(fn (Builder $pair) => $pair->where([
                        'resource_type' => $type,
                        'resource_id' => $id,
                    ]));
                }
            })
            ->orderByDesc('revision_number')
            ->get()
            ->unique(fn ($revision): string => "{$revision->resource_type}:{$revision->resource_id}")
            ->keyBy(fn ($revision): string => "{$revision->resource_type}:{$revision->resource_id}");
    }

    private function loadPublicationStates(Collection $pairs): Collection
    {
        $publicationTypes = $this->domains->publicationTypes();
        $publicationPairs = $pairs->filter(
            fn (array $pair): bool => in_array($pair[0], $publicationTypes, true),
        );
        if ($publicationPairs->isEmpty()) {
            return collect();
        }

        return ResourcePublicationState::query()
            ->whereIn('resource_type', $publicationTypes)
            ->where(function (Builder $query) use ($publicationPairs): void {
                foreach ($publicationPairs as [$type, $id]) {
                    $query->orWhere(fn (Builder $pair) => $pair->where([
                        'resource_type' => $type,
                        'resource_id' => $id,
                    ]));
                }
            })
            ->with('currentVersion.revision:id,revision_number')
            ->get()
            ->keyBy(fn ($state): string => "{$state->resource_type}:{$state->resource_id}");
    }

    private function present($submission, $resource, $latest, $publication, User $admin): array
    {
        $ownership = $this->ownership->present($submission, $admin);
        $current = $publication?->currentVersion;
        $definition = $this->domains->definition($submission->resource_type);

        return [
            'submissionId' => (string) $submission->id,
            'reviewKind' => $definition['kind'],
            'resourceType' => $submission->resource_type,
            'resourceTypeLabel' => $definition['label'],
            'resourceId' => (string) $submission->resource_id,
            'resourceLabel' => $resource?->name ?? 'Resource unavailable',
            'organization' => $resource?->organization ? [
                'id' => (string) $resource->organization->id,
                'name' => $resource->organization->name,
            ] : null,
            'submittedRevision' => [
                'id' => (string) $submission->revision->id,
                'number' => $submission->revision->revision_number,
            ],
            'latestRevision' => $latest ? [
                'id' => (string) $latest->id,
                'number' => $latest->revision_number,
            ] : null,
            'hasNewerDraftRevision' => $latest
                ? $latest->revision_number > $submission->revision->revision_number
                : false,
            'submittedBy' => $submission->submitter ? [
                'id' => (string) $submission->submitter->id,
                'name' => $submission->submitter->name,
                'inactive' => $submission->submitter->status !== 'active',
            ] : null,
            'submittedAt' => $submission->submitted_at?->toISOString(),
            'reviewState' => $ownership['state'],
            'reviewer' => $ownership['reviewer'],
            'reviewClaimedAt' => $submission->review_claimed_at?->toISOString(),
            'currentPublication' => $current ? [
                'id' => (string) $current->id,
                'number' => $current->publication_number,
                'revisionNumber' => $current->revision->revision_number,
            ] : null,
            'capabilities' => $ownership['capabilities'],
            'reviewDeepLink' => $this->domains->deepLink(
                $submission->resource_type,
                $submission->id,
            ),
        ];
    }

    private function assertAdmin(User $admin): void
    {
        abort_unless($admin->isPlatformAdmin() && $admin->status === 'active', 403);
    }
}
