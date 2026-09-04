<?php

namespace App\Notifications;

use App\Models\Notification;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevisionState;
use App\Models\ResourceShareRequest;
use App\Models\User;
use App\Services\ReviewDomainRegistry;

final class NotificationActionResolver
{
    public function __construct(
        private NotificationDeepLinkResolver $links,
        private NotificationActionRegistry $actions,
        private ReviewDomainRegistry $reviewDomains,
    ) {}

    public function resolve(Notification $n, User $u): array
    {
        if ((int) $n->user_id !== (int) $u->id) {
            return $this->result();
        }if ($n->type === 'revision.available') {
            return $this->revision($n, $u);
        }if (str_starts_with((string) $n->type, 'access.')) {
            return $this->share($n, $u);
        }if (str_contains((string) $n->type, '.publication.')) {
            return $this->publication($n, $u);
        }$link = $this->links->resolve($n, $u);

        return $this->result($link ? [$this->action('open', 'navigate', 'Open', $link)] : [], false, $link ? 'available' : 'no_longer_available', $link);
    }

    private function share(Notification $n, User $u): array
    {
        $id = $n->data['share_request_id'] ?? null;
        $s = $id ? ResourceShareRequest::find($id) : null;
        if (! $s) {
            return $this->result([], false, 'no_longer_available');
        }$a = [];
        $required = false;
        if ($n->type === 'access.requested' && $s->status === 'pending_recipient' && (int) $s->recipient_user_id === (int) $u->id) {
            $required = true;
            $a[] = $this->action('open_share_request', 'navigate', 'Accept or reject', "/app/shares/{$s->id}");
        }if ($n->type === 'access.awaiting_approval'
            && $s->status === 'awaiting_admin_approval'
            && $u->isPlatformAdmin()
            && User::whereKey($s->sender_user_id)->where('organization_id', $u->organization_id)->exists()) {
            $required = true;
            $a[] = $this->action('open_share_request', 'navigate', 'Open request', '/admin/access-requests');
        }$link = $a[0]['href'] ?? null;

        return $this->result($a, $required, $s->status, $link);
    }

    private function revision(Notification $n, User $u): array
    {
        $s = ResourceRevisionState::where(['resource_type' => $n->resource_type, 'resource_id' => $n->resource_id, 'user_id' => $u->id])->first();
        $latest = $n->data['latest_revision_id'] ?? null;
        $behind = $s && $latest && (int) $s->accepted_revision_id !== (int) $latest;
        $link = $behind ? $this->links->resolve($n, $u) : null;
        $a = [];
        if ($behind && $link) {
            $a[] = $this->action('review_changes', 'navigate', 'Review changes', $link);
            $a[] = $this->action('pull_update', 'navigate', 'Pull or ignore update', $link);
        }

return $this->result($a, (bool) $behind, $behind ? 'pending' : 'completed', $link);
    }

    private function publication(Notification $n, User $u): array
    {
        $id = $n->data['submission_id'] ?? null;
        $s = $id ? ResourcePublicationSubmission::find($id) : null;
        if (! $s) {
            return $this->result([], false, 'no_longer_available');
        }
        try {
            $definition = $this->reviewDomains->definition($s->resource_type);
            $currentOrganization = $definition['model']::whereKey($s->resource_id)
                ->where('organization_id', $u->organization_id)
                ->exists();
        } catch (\Throwable) {
            $currentOrganization = false;
        }
        if (! $currentOrganization) {
            return $this->result([], false, 'no_longer_available');
        }
        $pending = $currentOrganization && $s->status === 'submitted' && str_ends_with((string) $n->type, '.submitted') && $u->isPlatformAdmin();
        $link = $pending ? "/admin/review-center/{$s->resource_type}/{$s->id}" : $this->links->resolve($n, $u);

        return $this->result($link ? [$this->action($pending ? 'open_review' : 'open_resource', 'navigate', $pending ? 'Open review' : 'Open', $link)] : [], $pending, $s->status, $link);
    }

    private function action(string $key, string $kind, string $label, string $href): array
    {
        return $this->actions->navigation($key, $href, $label);
    }

    private function result(array $actions = [], bool $required = false, ?string $status = null, ?string $link = null): array
    {
        return ['actions' => $actions, 'actionRequired' => $required, 'status' => $status, 'deepLink' => $link];
    }
}
