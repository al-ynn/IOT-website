<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = ['organization_id','name','external_id','status','type','protocol','location','mac_address','last_seen','battery','firmware_version'];
    protected $casts = ['last_seen'=>'datetime','battery'=>'integer'];
    public function organization() { return $this->belongsTo(Organization::class); }
    public function telemetryRecords() { return $this->hasMany(TelemetryRecord::class); }
}
