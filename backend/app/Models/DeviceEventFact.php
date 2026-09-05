<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DeviceEventFact extends Model
{
    protected $fillable = ['device_id', 'event_definition_id', 'event_code', 'severity', 'event_name', 'message', 'value', 'occurred_at'];
    protected $casts = ['value' => 'json', 'occurred_at' => 'datetime'];
    public function device() { return $this->belongsTo(Device::class); }
    public function definition() { return $this->belongsTo(DeviceTemplateEventDefinition::class, 'event_definition_id'); }
}
