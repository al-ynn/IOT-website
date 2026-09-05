<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;

final class ResourceRevision extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'changed_sections' => 'array', 'snapshot_schema_version' => 'integer', 'revision_number' => 'integer', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $revision) {
            $revision->change_summary ??= 'Configuration updated';
        });
        static::updating(fn () => throw new LogicException('Resource revisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Resource revisions are immutable.'));
    }

    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    public function parent() { return $this->belongsTo(self::class, 'parent_revision_id'); }
}
