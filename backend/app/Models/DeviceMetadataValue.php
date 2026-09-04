<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DeviceMetadataValue extends Model
{
    protected $fillable = ['device_id', 'metadata_definition_id', 'value'];
    protected $casts = ['value' => 'json'];
    public function device() { return $this->belongsTo(Device::class); }
    public function definition() { return $this->belongsTo(DeviceTemplateMetadataDefinition::class, 'metadata_definition_id'); }
}
