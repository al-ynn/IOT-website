<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ResourceLifecycleState extends Model
{
    protected $fillable = ['resource_type', 'resource_id', 'state', 'disabled_by', 'disabled_at', 'restored_by', 'restored_at', 'archived_by', 'archived_at'];

    protected $casts = ['lifecycle_generation' => 'integer', 'disabled_at' => 'datetime', 'restored_at' => 'datetime', 'archived_at' => 'datetime'];

    public function disabler()
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    public function restorer()
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function archiver()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
