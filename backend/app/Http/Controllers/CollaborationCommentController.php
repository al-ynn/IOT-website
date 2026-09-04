<?php

namespace App\Http\Controllers;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Collaboration\CommentAnchorRegistry;
use App\Models\CollaborationComment;
use App\Models\CollaborationThread;
use App\Services\CollaborationCommentService;
use App\Services\CommentAnchorResolver;
use App\Services\RevisionComparisonService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollaborationCommentController extends Controller
{
    public function __construct(
        private CollaborationCommentService $service,
        private CollaborationResourceRegistry $registry,
        private RevisionComparisonService $comparisons,
        private CommentAnchorRegistry $anchors,
        private CommentAnchorResolver $anchorResolver,
    ) {}

    private function rules(bool $thread = false): array
    {
        return [
            ...($thread ? [
                'anchor_type' => ['nullable', 'string', 'max:50'],
                'anchor_key' => ['nullable', 'string', 'max:100'],
                'origin_revision_id' => ['nullable', 'integer'],
            ] : [
                'parent_comment_id' => ['nullable', 'integer'],
                'anchor_type' => ['prohibited'],
                'anchor_key' => ['prohibited'],
                'origin_revision_id' => ['prohibited'],
            ]),
            'body' => ['required', 'string', 'max:5000'],
            'mention_ids' => ['array', 'max:20'],
            'mention_ids.*' => ['integer', 'distinct'],
            'created_by' => ['prohibited'],
            'anchor_schema_version' => ['prohibited'],
            'currentLabel' => ['prohibited'],
            'originLabel' => ['prohibited'],
            'changedSinceOrigin' => ['prohibited'],
            'movedSinceOrigin' => ['prohibited'],
            'removed' => ['prohibited'],
            'navigation' => ['prohibited'],
            'selector' => ['prohibited'],
            'path' => ['prohibited'],
        ];
    }

    public function index(Request $r, string $type, string $resource)
    {
        $this->registry->resolve($r->user(), new CollaborationResourceReference($type, $resource), 'view');
        $f = $r->validate(['status' => ['nullable', Rule::in(['open', 'resolved'])], 'anchor_type' => ['nullable', Rule::in($this->anchors->allowedTypes($type))], 'anchor_key' => ['nullable', 'string', 'max:100']]);
        $q = CollaborationThread::where('resource_type', $type)->where('resource_id', $resource)->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($f['anchor_type'] ?? null, fn ($q, $a) => $q->where('anchor_type', $a))->when(array_key_exists('anchor_key', $f), fn ($q) => $f['anchor_key'] === null ? $q->whereNull('anchor_key') : $q->where('anchor_key', $f['anchor_key']))->with(['creator:id,name', 'resolver:id,name', 'comments.author:id,name', 'comments.mentions:id,name', 'acknowledgments.user:id,name'])->latest();

        return response()->json($q->paginate(20)->through(fn ($t) => $this->thread($r, $t)));
    }

    public function store(Request $r, string $type, string $resource)
    {
        $t = $this->service->createThread($r->user(), $type, (int) $resource, $r->validate($this->rules(true)));

        return response()->json($this->thread($r, $this->load($t)), 201);
    }

    public function show(Request $r, CollaborationThread $thread)
    {
        $this->service->resolve($r->user(), $thread, 'view');

        return $this->thread($r, $this->load($thread));
    }

    public function reply(Request $r, CollaborationThread $thread)
    {
        $c = $this->service->comment($r->user(), $thread, $r->validate($this->rules()));

        return response()->json($this->comment($c->load(['author:id,name', 'mentions:id,name'])), 201);
    }

    public function update(Request $r, CollaborationComment $comment)
    {
        $comment = $this->service->edit($r->user(), $comment, $r->validate(['body' => 'required|string|max:5000', 'thread_id' => 'prohibited', 'resource_type' => 'prohibited', 'resource_id' => 'prohibited', 'anchor_type' => 'prohibited', 'anchor_key' => 'prohibited', 'created_by' => 'prohibited']));

        return $this->comment($comment->load(['author:id,name', 'mentions:id,name']));
    }

    public function acknowledge(Request $r, CollaborationThread $thread)
    {
        $this->service->acknowledge($r->user(), $thread);

        return $this->thread($r, $this->load($thread));
    }

    public function resolve(Request $r, CollaborationThread $thread)
    {
        return $this->thread($r, $this->load($this->service->transition($r->user(), $thread, 'resolved')));
    }

    public function reopen(Request $r, CollaborationThread $thread)
    {
        return $this->thread($r, $this->load($this->service->transition($r->user(), $thread, 'open')));
    }

    public function candidates(Request $r, string $type, string $resource)
    {
        $f = $r->validate(['search' => ['nullable', 'string', 'max:100']]);

        return $this->service->candidates($r->user(), $type, (int) $resource, $f['search'] ?? null)->map(fn ($u) => ['id' => (string) $u->id, 'name' => $u->name]);
    }

    private function load($t)
    {
        return $t->load(['creator:id,name', 'resolver:id,name', 'comments.author:id,name', 'comments.mentions:id,name', 'acknowledgments.user:id,name', 'transitions']);
    }

    private function thread(Request $r, $t)
    {
        return ['id' => (string) $t->id, 'resourceType' => $t->resource_type, 'resourceId' => (string) $t->resource_id, 'anchor' => ['type' => $t->anchor_type, 'key' => $t->anchor_key, 'revisionId' => $t->anchor_revision_id ? (string) $t->anchor_revision_id : null, 'schemaVersion' => (int) ($t->anchor_schema_version ?: 1)], 'anchorContext' => $this->anchorResolver->resolve($r->user(), $t), 'revisionContext' => $this->comparisons->threadContext($r->user(), $t), 'status' => $t->status, 'creator' => $t->creator ? ['id' => (string) $t->creator->id, 'name' => $t->creator->name] : null, 'resolvedBy' => $t->resolver ? ['id' => (string) $t->resolver->id, 'name' => $t->resolver->name] : null, 'resolvedAt' => $t->resolved_at?->toISOString(), 'comments' => $t->comments->map(fn ($c) => $this->comment($c))->values(), 'acknowledgments' => $t->acknowledgments->map(fn ($a) => ['user' => ['id' => (string) $a->user_id, 'name' => $a->user?->name], 'acknowledgedAt' => $a->acknowledged_at->toISOString()])->values(), 'createdAt' => $t->created_at->toISOString()];
    }

    private function comment($c)
    {
        return ['id' => (string) $c->id, 'parentCommentId' => $c->parent_comment_id ? (string) $c->parent_comment_id : null, 'body' => $c->body, 'author' => $c->author ? ['id' => (string) $c->author->id, 'name' => $c->author->name] : null, 'mentions' => $c->mentions->map(fn ($u) => ['id' => (string) $u->id, 'name' => $u->name])->values(), 'editedAt' => $c->edited_at?->toISOString(), 'createdAt' => $c->created_at->toISOString()];
    }
}
