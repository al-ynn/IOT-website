<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryRecord extends Model
{
    protected $fillable = ['device_id', 'key', 'value', 'typed_value', 'unit', 'recorded_at'];
    protected $casts = ['value' => 'float', 'typed_value'=>'json', 'recorded_at' => 'datetime'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
