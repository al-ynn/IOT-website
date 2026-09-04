<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class ResourcePublicationVersion extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'published_at' => 'datetime', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Publication versions are immutable.'));
        static::deleting(fn () => throw new LogicException('Publication versions cannot be deleted through the application model.'));
    }

    public function revision() { return $this->belongsTo(ResourceRevision::class, 'resource_revision_id'); }
    public function submission() { return $this->belongsTo(ResourcePublicationSubmission::class, 'review_submission_id'); }
    public function publisher() { return $this->belongsTo(User::class, 'published_by'); }
    public function previousVersion() { return $this->belongsTo(self::class, 'previous_publication_version_id'); }
}
