<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class CollaborationActivityRegistry
{
    public function __construct(private ResourceSectionRegistry $sections) {}

    public const CATEGORIES = ['changes', 'comments', 'sharing', 'review', 'publication'];
    public const RESOURCES = ['device', 'device_template', 'dashboard', 'automation', 'report', 'firmware', 'location', 'webhook'];

    private const EVENTS = [
        'resource.revised' => ['category' => 'changes', 'summary' => 'Updated :resource.'],
        'comment.created' => ['category' => 'comments', 'summary' => 'Commented on :resource.'],
        'comment.replied' => ['category' => 'comments', 'summary' => 'Replied to a comment on :resource.'],
        'comment.resolved' => ['category' => 'comments', 'summary' => 'Resolved a comment thread on :resource.'],
        'comment.reopened' => ['category' => 'comments', 'summary' => 'Reopened a comment thread on :resource.'],
        'share.requested' => ['category' => 'sharing', 'summary' => 'Requested access to :resource.'],
        'share.accepted' => ['category' => 'sharing', 'summary' => 'Accepted access to :resource.'],
        'share.rejected' => ['category' => 'sharing', 'summary' => 'Rejected access to :resource.'],
        'review.submitted' => ['category' => 'review', 'summary' => 'Submitted :resource for review.'],
        'review.claimed' => ['category' => 'review', 'summary' => 'Started review of :resource.'],
        'review.released' => ['category' => 'review', 'summary' => 'Released review of :resource.'],
        'review.taken_over' => ['category' => 'review', 'summary' => 'Took over review of :resource.'],
        'review.changes_requested' => ['category' => 'review', 'summary' => 'Requested changes to :resource.'],
        'review.rejected' => ['category' => 'review', 'summary' => 'Rejected publication of :resource.'],
        'review.approved' => ['category' => 'review', 'summary' => 'Approved publication of :resource.'],
        'publication.published' => ['category' => 'publication', 'summary' => 'Published :resource.'],
    ];

    public function event(string $key): array
    {
        if (!isset(self::EVENTS[$key])) throw ValidationException::withMessages(['event_type' => ['Unsupported collaboration activity event type.']]);
        return self::EVENTS[$key];
    }

    public function validateFilters(array $filters): array
    {
        if (($filters['category'] ?? null) && !in_array($filters['category'], self::CATEGORIES, true)) throw ValidationException::withMessages(['category' => ['Unsupported activity category.']]);
        if (($filters['resource_type'] ?? null) && !in_array($filters['resource_type'], self::RESOURCES, true)) throw ValidationException::withMessages(['resource_type' => ['Unsupported activity resource type.']]);
        if ($filters['section'] ?? null) { if (!($filters['resource_type'] ?? null)) throw ValidationException::withMessages(['resource_type' => ['Choose a resource type before filtering by section.']]); $filters['section'] = $this->sections->validate($filters['resource_type'], $filters['section']); }
        return $filters;
    }

    public function summary(string $key, string $label): string
    {
        return str_replace(':resource', $label, $this->event($key)['summary']);
    }

    public function metadata(): array
    {
        return ['categories' => self::CATEGORIES, 'resourceTypes' => self::RESOURCES, 'eventTypes' => array_keys(self::EVENTS), 'sectionsByResource' => $this->sections->metadata()];
    }
}
