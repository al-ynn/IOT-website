<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryRecord extends Model
{
    protected $fillable = ['device_id', 'key', 'value', 'unit', 'recorded_at'];
    protected $casts = ['value' => 'float', 'recorded_at' => 'datetime'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
