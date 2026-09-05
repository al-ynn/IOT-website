<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ResourceSaveIdempotency extends Model
{
    protected $fillable = [
        'user_id', 'operation_scope', 'key_hash', 'request_fingerprint',
        'resource_type', 'resource_id', 'base_revision_id', 'changed',
        'committed_revision_id', 'committed_revision_number', 'completed_at', 'expires_at',
    ];

    protected $casts = [
        'changed' => 'boolean',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
