<?php

namespace App\Models;

use App\Services\ResourceRevisionStateService;
use Illuminate\Database\Eloquent\Model;

class CollaborationThread extends Model
{
    protected $attributes = ['anchor_type' => 'resource', 'status' => 'open'];

    protected $fillable = ['resource_type', 'resource_id', 'anchor_type', 'anchor_key', 'anchor_revision_id', 'anchor_schema_version', 'status', 'created_by', 'resolved_by', 'resolved_at'];

    protected $casts = ['resolved_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $thread) {
            if ($thread->anchor_revision_id !== null) {
                return;
            }
            $states = app(ResourceRevisionStateService::class);
            if ($thread->resource_type === 'device' && auth()->check() && $states->hasHistory('device', $thread->resource_id)) {
                $state = $states->state(auth()->user(), 'device', $thread->resource_id);
                $thread->anchor_revision_id = $state['acceptedRevision']['id'];

                return;
            }
            $thread->anchor_revision_id = ResourceRevision::query()
                ->where('resource_type', $thread->resource_type)
                ->where('resource_id', $thread->resource_id)
                ->orderByDesc('revision_number')->value('id');
        });
    }

    public function comments()
    {
        return $this->hasMany(CollaborationComment::class, 'thread_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function acknowledgments()
    {
        return $this->hasMany(CommentAcknowledgment::class, 'thread_id');
    }

    public function transitions()
    {
        return $this->hasMany(CollaborationThreadTransition::class, 'thread_id');
    }

    public function anchorRevision()
    {
        return $this->belongsTo(ResourceRevision::class, 'anchor_revision_id');
    }
}
