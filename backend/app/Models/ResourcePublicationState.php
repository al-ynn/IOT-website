<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ResourcePublicationState extends Model
{
    protected $guarded = [];
    public function approvedRevision() { return $this->belongsTo(ResourceRevision::class, 'approved_revision_id'); }
    public function currentVersion() { return $this->belongsTo(ResourcePublicationVersion::class, 'current_publication_version_id'); }
}
