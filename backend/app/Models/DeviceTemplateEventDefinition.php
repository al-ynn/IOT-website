<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DeviceTemplateEventDefinition extends Model
{
    public const SEVERITIES = ['info', 'warning', 'critical'];
    protected $fillable = ['device_template_id', 'code', 'name', 'severity', 'description', 'enabled'];
    protected $casts = ['enabled' => 'boolean'];
    public function template() { return $this->belongsTo(DeviceTemplate::class, 'device_template_id'); }
    public function facts() { return $this->hasMany(DeviceEventFact::class, 'event_definition_id'); }
}
