<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ResourcePublicationSubmission extends Model
{
    public const ACTIVE_STATUSES = ['submitted'];
    protected $guarded = [];
    protected $casts = ['submitted_at' => 'datetime', 'review_claimed_at' => 'datetime', 'decision_at' => 'datetime'];
    public function revision() { return $this->belongsTo(ResourceRevision::class, 'submitted_revision_id'); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function decisionMaker() { return $this->belongsTo(User::class, 'decision_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function events() { return $this->hasMany(ResourceReviewEvent::class, 'submission_id'); }
    public function publicationVersion() { return $this->hasOne(ResourcePublicationVersion::class, 'review_submission_id'); }

}
