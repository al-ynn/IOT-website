<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use SoftDeletes;

    public const TYPES = ['device_telemetry', 'device_summary', 'operational_events'];

    protected $fillable = ['organization_id', 'name', 'description', 'report_type', 'configuration', 'created_by', 'updated_by'];

    protected $casts = ['configuration' => 'array'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function collaborators()
    {
        return $this->hasMany(ResourceCollaborator::class, 'resource_id')->where('resource_type', 'report');
    }

    public function runs()
    {
        return $this->hasMany(ReportRun::class);
    }
}
