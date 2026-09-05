<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\CollaborationThread;
use App\Models\DashboardWidget;
use App\Models\DeviceParameter;
use App\Models\ResourceRevision;
use App\Models\User;

final class CommentAnchorResolver
{
    public function __construct(
        private CollaborationResourceRegistry $resources,
        private ResourceSectionRegistry $sections,
        private RevisionComparisonService $comparisons,
        private ResourceRevisionAuthorizationService $revisions,
    ) {}

    public function resolve(User $user, CollaborationThread $thread): array
    {
        $this->resources->resolve($user, new CollaborationResourceReference($thread->resource_type, $thread->resource_id), 'view');
        $current = $this->current($thread);
        $origin = $this->origin($user, $thread);
        try {
            $change = $this->comparisons->threadContext($user, $thread);
        } catch (\Throwable) {
            // Not every revisionable resource has a structured comparison adapter.
            // Lack of proof is unknown, never unchanged.
            $change = null;
        }

        if (! $current['exists']) {
            $change = [
                'status' => 'removed',
                'message' => 'Original context no longer exists in the current configuration.',
                'focus' => $current['focus'],
                ...$this->revisionRange($origin, $thread),
            ];
        }

        return [
            'schemaVersion' => (int) ($thread->anchor_schema_version ?: 1),
            'type' => $this->canonicalType($thread->anchor_type),
            'semanticKey' => $thread->anchor_key,
            'current' => $current,
            'origin' => $origin,
            'change' => $change ?? [
                'status' => 'unknown',
                'message' => $origin ? 'Original context is available, but change status could not be determined.' : null,
                'focus' => $current['focus'],
            ],
        ];
    }

    private function current(CollaborationThread $thread): array
    {
        if ($thread->anchor_type === 'resource') {
            return $this->state(true, 'Resource', null);
        }
        if ($thread->anchor_type === 'section') {
            try {
                $section = $this->sections->section($thread->resource_type, (string) $thread->anchor_key);

                return $this->state(true, $section['label'], "section:{$section['key']}", $section['tab']);
            } catch (\Throwable) {
                return $this->state(false, 'Former section', "section:{$thread->anchor_key}");
            }
        }
        if ($thread->resource_type === 'location' && in_array($thread->anchor_type, ['metadata', 'name', 'description'], true)) {
            return $this->state(true, ucfirst($thread->anchor_type), "section:{$thread->anchor_type}", 'overview');
        }
        if ($thread->anchor_type === 'parameter') {
            $item = DeviceParameter::query()->whereKey($thread->anchor_key)->where('device_id', $thread->resource_id)->first();

            return $this->state((bool) $item, $item?->name ?? 'Former parameter', "parameter:{$thread->anchor_key}", 'parameters');
        }
        if ($thread->anchor_type === 'dashboard_widget') {
            $query = DashboardWidget::query()->whereKey($thread->anchor_key);
            $thread->resource_type === 'dashboard'
                ? $query->where('dashboard_id', $thread->resource_id)
                : $query->whereHas('dashboard', fn ($q) => $q->where('device_id', $thread->resource_id));
            $item = $query->first();

            return $this->state((bool) $item, $item?->title ?? 'Former widget', "dashboard_widget:{$thread->anchor_key}", 'dashboard');
        }

        return $this->state(false, 'Legacy context unavailable', null);
    }

    private function origin(User $user, CollaborationThread $thread): ?array
    {
        if (! $thread->anchor_revision_id) {
            return null;
        }
        try {
            $revision = $this->revisions->revision($user, $thread->resource_type, $thread->resource_id, $thread->anchor_revision_id);

            return ['revisionId' => (string) $revision->id, 'revisionNumber' => $revision->revision_number, 'available' => true];
        } catch (\Throwable) {
            return ['revisionId' => null, 'revisionNumber' => null, 'available' => false];
        }
    }

    private function revisionRange(?array $origin, CollaborationThread $thread): array
    {
        if (! ($origin['available'] ?? false)) {
            return [];
        }
        $latest = ResourceRevision::query()->where('resource_type', $thread->resource_type)->where('resource_id', $thread->resource_id)->latest('revision_number')->first();

        return $latest ? ['fromRevisionId' => $origin['revisionId'], 'toRevisionId' => (string) $latest->id] : [];
    }

    private function state(bool $exists, string $label, ?string $focus, ?string $tab = null): array
    {
        return ['exists' => $exists, 'label' => $label, 'focus' => $focus, 'navigation' => $exists ? ['tab' => $tab, 'focus' => $focus] : null];
    }

    private function canonicalType(string $type): string
    {
        return match ($type) {
            'resource' => 'RESOURCE',
            'section', 'metadata', 'name', 'description' => 'SECTION',
            'dashboard_widget', 'parameter' => 'CHILD_ENTITY',
            default => 'UNRESOLVABLE',
        };
    }
}
