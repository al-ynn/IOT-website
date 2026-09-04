<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DeviceTemplateMetadataDefinition extends Model
{
    protected $fillable = ['device_template_id', 'key', 'name', 'data_type', 'description', 'required', 'configuration', 'sort_order'];
    protected $casts = ['required' => 'boolean', 'configuration' => 'array'];
    public function template() { return $this->belongsTo(DeviceTemplate::class, 'device_template_id'); }
    public function values() { return $this->hasMany(DeviceMetadataValue::class, 'metadata_definition_id'); }
}
