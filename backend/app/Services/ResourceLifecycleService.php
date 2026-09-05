<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Lifecycle\ResourceLifecyclePolicyRegistry;
use App\Models\ResourceLifecycleState;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResourceLifecycleService
{
    public function __construct(private CollaborationResourceRegistry $resources, private ResourceLifecyclePolicyRegistry $policies) {}

    public function state(string $type, int|string $id): string
    {
        return ResourceLifecycleState::where(['resource_type' => $type, 'resource_id' => $id])->value('state') ?? 'active';
    }

    public function generation(string $type, int|string $id): int
    {
        return (int) (ResourceLifecycleState::where(['resource_type' => $type, 'resource_id' => $id])->value('lifecycle_generation') ?? 1);
    }

    public function allowsGeneration(string $type, int|string $id, int $generation): bool
    {
        return $this->state($type, $id) === 'active' && hash_equals((string) $this->generation($type, $id), (string) $generation);
    }

    public function show(User $actor, string $type, int $id): array
    {
        $resolved = $this->resources->resolve($actor, new CollaborationResourceReference($type, $id), 'view');

        return $this->data($type, $resolved->resource);
    }

    public function disable(User $admin, string $type, int $id, ?string $expected = null, ?int $expectedGeneration = null): array
    {
        return $this->transition($admin, $type, $id, 'disabled', $expected, $expectedGeneration);
    }

    public function restore(User $admin, string $type, int $id, ?string $expected = null, ?int $expectedGeneration = null): array
    {
        return $this->transition($admin, $type, $id, 'active', $expected, $expectedGeneration);
    }

    public function archive(User $admin, string $type, int $id, ?string $expected = null, ?int $expectedGeneration = null): array
    {
        return $this->transition($admin, $type, $id, 'archived', $expected, $expectedGeneration);
    }

    public function assertActive(string $type, int|string $id, string $message = 'This resource is not Active.'): void
    {
        abort_unless($this->state($type, $id) === 'active', 409, $message);
    }

    public function lockActiveResource(User $actor, string $type, int $id, string $ability): Model
    {
        $this->resources->resolve($actor, new CollaborationResourceReference($type, $id), $ability);

        return $this->lockActiveAuthority($type, $id);
    }

    public function lockActiveAuthority(string $type, int $id): Model
    {
        $this->policies->get($type);
        $modelClass = $this->resources->definition($type)->modelClass;
        $resource = $modelClass::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        $this->assertActive($type, $id);

        return $resource;
    }

    private function transition(User $admin, string $type, int $id, string $target, ?string $expected, ?int $expectedGeneration): array
    {
        $policy = $this->policies->get($type);
        if ($target === 'archived' && ! $policy->archive) {
            throw ValidationException::withMessages(['state' => ['Archive is not supported for this resource type.']]);
        }
        $resolved = $this->resources->resolve($admin, new CollaborationResourceReference($type, $id), 'administer');
        $modelClass = $resolved->resource::class;
        [$resource,$state,$changed] = DB::transaction(function () use ($admin, $type, $id, $target, $expected, $expectedGeneration, $modelClass) {
            $resource = $modelClass::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($admin->isPlatformAdmin(), 403);
            $this->resources->resolve($admin, new CollaborationResourceReference($type, $id), 'administer');
            $row = ResourceLifecycleState::where(['resource_type' => $type, 'resource_id' => $id])->lockForUpdate()->first();
            $current = $row?->state ?? 'active';
            $generation = (int) ($row?->lifecycle_generation ?? 1);
            if ($current === $target) {
                return [$resource, $row ?? new ResourceLifecycleState(['resource_type' => $type, 'resource_id' => $id, 'state' => 'active', 'lifecycle_generation' => $generation]), false];
            }
            if ($expected !== null && $expected !== $current) {
                $this->conflict($current, $generation);
            }
            if ($expectedGeneration !== null && $expectedGeneration !== $generation) {
                $this->conflict($current, $generation);
            }
            if ($target === 'disabled' && $current !== 'active') {
                $this->invalid($current, $target);
            }
            if ($target === 'archived' && ! in_array($current, ['active', 'disabled'], true)) {
                $this->invalid($current, $target);
            }
            if ($target === 'active' && ! in_array($current, ['disabled', 'archived'], true)) {
                $this->invalid($current, $target);
            }
            $row ??= new ResourceLifecycleState(['resource_type' => $type, 'resource_id' => $id]);
            $values = ['state' => $target];
            if ($target === 'disabled') {
                $values += ['disabled_by' => $admin->id, 'disabled_at' => now()];
            }if ($target === 'archived') {
                $values += ['archived_by' => $admin->id, 'archived_at' => now()];
            }if ($target === 'active') {
                $values += ['restored_by' => $admin->id, 'restored_at' => now()];
            }
            $row->fill($values);
            $row->setAttribute('lifecycle_generation', $generation + 1);
            $row->save();
            $this->applyDisableSideEffects($type, $resource, $target);

            return [$resource->refresh(), $row, true];
        });

        return $this->data($type, $resource, $state->exists ? $state : null, $changed);
    }

    private function applyDisableSideEffects(string $type, Model $resource, string $target): void
    {
        if (! in_array($target, ['disabled', 'archived'], true)) {
            return;
        }if ($type === 'webhook' && $resource->getAttribute('enabled')) {
            $resource->forceFill(['enabled' => false])->save();
        }if ($type === 'automation' && $resource->getAttribute('enabled')) {
            $resource->forceFill(['enabled' => false])->save();
        }
    }

    private function invalid(string $from, string $to): never
    {
        throw ValidationException::withMessages(['state' => ["Invalid lifecycle transition from {$from} to {$to}."]]);
    }

    private function conflict(string $state, int $generation): never
    {
        throw ValidationException::withMessages(['expectedLifecycle' => ["Lifecycle changed; current state is {$state} at generation {$generation}."]]);
    }

    private function data(string $type, Model $resource, ?ResourceLifecycleState $row = null, bool $changed = false): array
    {
        $row ??= ResourceLifecycleState::where(['resource_type' => $type, 'resource_id' => $resource->getKey()])->first();
        $state = $row?->state ?? 'active';

        return ['resourceType' => $type, 'resourceId' => (string) $resource->getKey(), 'resourceLabel' => $resource->getAttribute('name'), 'state' => $state, 'label' => ucfirst($state), 'changed' => $changed, 'lifecycleGeneration' => (int) ($row?->lifecycle_generation ?? 1), 'transitionedAt' => $changed ? $row?->updated_at?->toISOString() : null, 'disabledAt' => $row?->disabled_at?->toISOString(), 'restoredAt' => $row?->restored_at?->toISOString(), 'archivedAt' => $row?->archived_at?->toISOString(), 'capabilities' => ['canDisable' => $state === 'active', 'canRestore' => in_array($state, ['disabled', 'archived'], true), 'canArchive' => $state !== 'archived' && $this->policies->get($type)->archive]];
    }
}
