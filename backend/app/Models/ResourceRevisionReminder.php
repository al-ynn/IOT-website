<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ResourceRevisionReminder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'remind_at' => 'datetime',
        'sent_at' => 'datetime',
        'generation' => 'integer',
        'lifecycle_generation' => 'integer',
    ];

    public function revisionState()
    {
        return $this->belongsTo(ResourceRevisionState::class, 'resource_revision_state_id');
    }

    public function targetRevision()
    {
        return $this->belongsTo(ResourceRevision::class, 'target_revision_id');
    }
}
