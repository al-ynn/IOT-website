<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Collaboration\CommentAnchorRegistry;
use App\Models\CollaborationComment;
use App\Models\CollaborationThread;
use App\Models\DeviceAccessAssignment;
use App\Models\ResourceCollaborator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CollaborationCommentService
{
    public function __construct(
        private CollaborationResourceRegistry $registry,
        private CommentAnchorRegistry $anchors,
        private ResourceRevisionAuthorizationService $revisions,
    ) {}

    public function resolve(User $u, CollaborationThread $t, string $ability = 'comment')
    {
        return $this->registry->resolve($u, new CollaborationResourceReference($t->resource_type, $t->resource_id), $ability);
    }

    public function createThread(User $u, string $type, int $id, array $data): CollaborationThread
    {
        $r = $this->registry->resolve($u, new CollaborationResourceReference($type, $id), 'comment');
        $anchor = $data['anchor_type'] ?? 'resource';
        $key = $data['anchor_key'] ?? null;
        $this->anchors->validate($type, $r->resource, $anchor, $key);
        $originRevisionId = $data['origin_revision_id'] ?? null;
        if ($originRevisionId !== null) {
            $this->revisions->revision($u, $type, $id, $originRevisionId);
        }

        return DB::transaction(function () use ($u, $type, $id, $data, $anchor, $key, $originRevisionId) {
            $t = CollaborationThread::create(['resource_type' => $type, 'resource_id' => $id, 'anchor_type' => $anchor, 'anchor_key' => $key, 'anchor_revision_id' => $originRevisionId, 'anchor_schema_version' => 1, 'created_by' => $u->id]);
            $this->comment($u, $t, $data);

            return $t;
        });
    }

    public function comment(User $u, CollaborationThread $t, array $data): CollaborationComment
    {
        return DB::transaction(function () use ($u, $t, $data) {
            $t = CollaborationThread::query()->lockForUpdate()->findOrFail($t->id);
            $this->resolve($u, $t);
            if ($t->status !== 'open') {
                throw ValidationException::withMessages(['thread' => ['Resolved threads must be reopened before replying.']]);
            }if (! empty($data['parent_comment_id']) && ! CollaborationComment::query()->whereKey($data['parent_comment_id'])->where('thread_id', $t->id)->exists()) {
                throw ValidationException::withMessages(['parent_comment_id' => ['Parent comment must belong to this thread.']]);
            }$body = $this->body($data['body']);
            $c = $t->comments()->create(['parent_comment_id' => $data['parent_comment_id'] ?? null, 'body' => $body, 'created_by' => $u->id]);
            $mentions = $this->eligibleMentions($u, $t, $data['mention_ids'] ?? []);
            $c->mentions()->sync($mentions->pluck('id'));
            foreach ($mentions as $m) {
                if ($m->id !== $u->id) {
                    $this->notify($m, $u, $t, $c, 'comment.mentioned', 'mentions', "{$u->name} mentioned you in a comment.");
                }
            }if ($t->created_by && $t->created_by !== $u->id && ! $mentions->contains('id', $t->created_by)) {
                $this->notify(User::find($t->created_by), $u, $t, $c, 'comment.created', 'updates', "{$u->name} replied to your thread.");
            }

            return $c;
        });
    }

    private function eligibleMentions(User $actor, CollaborationThread $t, array $ids)
    {
        $users = User::whereIn('id', array_unique($ids))->where('status', 'active')->get();
        if ($users->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['mention_ids' => ['One or more mentioned users are not eligible.']]);
        }foreach ($users as $u) {
            try {
                $this->resolve($u, $t, 'view');
            } catch (\Throwable) {
                throw ValidationException::withMessages(['mention_ids' => ['Mentioned users must already have resource access.']]);
            }if (! $u->isPlatformAdmin() && (int) $u->organization_id !== (int) $actor->organization_id) {
                throw ValidationException::withMessages(['mention_ids' => ['Mentioned Staff must be in the same Organization.']]);
            }
        }

        return $users;
    }

    public function canResolve(User $u, CollaborationThread $t): bool
    {
        if ($u->isPlatformAdmin() || $t->created_by === $u->id) {
            return true;
        }try {
            $this->resolve($u, $t, 'edit');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function transition(User $u, CollaborationThread $t, string $to): CollaborationThread
    {
        return DB::transaction(function () use ($u, $t, $to) {
            $t = CollaborationThread::query()->lockForUpdate()->findOrFail($t->id);
            $this->resolve($u, $t);
            abort_unless($this->canResolve($u, $t), 403);
            $from = $t->status;
            if ($from === $to) {
                return $t;
            }$t->transitions()->create(['from_status' => $from, 'to_status' => $to, 'actor_id' => $u->id, 'created_at' => now()]);
            $t->update(['status' => $to, 'resolved_by' => $to === 'resolved' ? $u->id : null, 'resolved_at' => $to === 'resolved' ? now() : null]);

            return $t->refresh();
        });
    }

    public function acknowledge(User $u, CollaborationThread $t): void
    {
        DB::transaction(function () use ($u, $t) {
            $t = CollaborationThread::query()->lockForUpdate()->findOrFail($t->id);
            $this->resolve($u, $t);
            $t->acknowledgments()->firstOrCreate(['user_id' => $u->id], ['acknowledged_at' => now()]);
        });
    }

    public function edit(User $u, CollaborationComment $comment, array $data): CollaborationComment
    {
        return DB::transaction(function () use ($u, $comment, $data) {
            $threadId = CollaborationComment::query()->whereKey($comment->id)->value('thread_id');
            abort_unless($threadId, 404);
            $t = CollaborationThread::query()->lockForUpdate()->findOrFail($threadId);
            $comment = CollaborationComment::query()->where('thread_id', $t->id)->lockForUpdate()->findOrFail($comment->id);
            $this->resolve($u, $t);
            abort_unless((int) $comment->created_by === (int) $u->id && $t->status === 'open', 403);
            $comment->update(['body' => $this->body($data['body']), 'edited_at' => now()]);

            return $comment->refresh();
        });
    }

    public function candidates(User $actor, string $type, int $id, ?string $search = null)
    {
        $resolved = $this->registry->resolve($actor, new CollaborationResourceReference($type, $id), 'comment');
        $resource = $resolved->resource;
        $ids = match ($type) {
            'device' => DeviceAccessAssignment::query()->where('device_id', $id)->select('user_id'),'dashboard','location' => ResourceCollaborator::query()->where(['resource_type' => $type, 'resource_id' => $id])->select('user_id'),default => throw ValidationException::withMessages(['resource_type' => ['Comment candidates are unavailable for this resource type.']])
        };
        $q = User::query()->select(['id', 'name'])->where('status', 'active')->where('organization_id', $resource->getAttribute('organization_id'))->where(function ($q) use ($ids, $type, $resource) {
            $q->whereIn('id', $ids);
            if ($type === 'dashboard' && $resource->getAttribute('owner_user_id')) {
                $q->orWhereKey($resource->getAttribute('owner_user_id'));
            }
        });
        if ($search !== null && $search !== '') {
            $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%');
        }

        return $q->orderBy('name')->limit(25)->get();
    }

    private function body(string $body): string
    {
        if (! preg_match('//u', $body) || str_contains($body, "\0") || trim($body) === '') {
            throw ValidationException::withMessages(['body' => ['Comment body must be non-empty valid plain text.']]);
        }

        return str_replace(["\r\n", "\r"], "\n", $body);
    }

    private function notify(?User $recipient, User $actor, CollaborationThread $t, CollaborationComment $c, string $type, string $category, string $body): void
    {
        if (! $recipient) {
            return;
        }
        app(NotificationOutboxService::class)->recordNotification($recipient, $type, "comment:{$c->id}", ['organization_id' => $recipient->organization_id ?? $actor->organization_id, 'actor_id' => $actor->id, 'resource_type' => $t->resource_type, 'resource_id' => $t->resource_id, 'action_url' => null, 'data' => ['thread_id' => $t->id, 'comment_id' => $c->id, 'anchor_type' => $t->anchor_type, 'anchor_key' => $t->anchor_key], 'title' => $type === 'comment.mentioned' ? 'You were mentioned' : 'New comment', 'message' => $body, 'severity' => 'info']);
    }
}
