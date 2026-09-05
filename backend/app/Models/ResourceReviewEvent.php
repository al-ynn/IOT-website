<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class ResourceReviewEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Review history is append-only.'));
        static::deleting(fn () => throw new LogicException('Review history cannot be deleted through the application model.'));
    }

    public function submission() { return $this->belongsTo(ResourcePublicationSubmission::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function revision() { return $this->belongsTo(ResourceRevision::class, 'resource_revision_id'); }
    public function previousReviewer() { return $this->belongsTo(User::class, 'previous_reviewer_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function previousApprovedRevision() { return $this->belongsTo(ResourceRevision::class, 'previous_approved_revision_id'); }
    public function approvedRevision() { return $this->belongsTo(ResourceRevision::class, 'approved_revision_id'); }
}
