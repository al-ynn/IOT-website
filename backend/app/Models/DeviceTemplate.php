<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceTemplate extends Model
{
    protected $fillable = ['organization_id', 'name', 'description', 'device_type', 'protocol', 'dashboard_configuration', 'created_by'];
    protected $casts = ['dashboard_configuration' => 'array'];

    protected static function booted(): void
    {
        static::created(function (self $template): void {
            if ($template->created_by) ResourceCollaborator::firstOrCreate(
                ['resource_type' => 'device_template', 'resource_id' => $template->id, 'user_id' => $template->created_by],
                ['permission' => 'edit', 'granted_by' => $template->created_by],
            );
        });
    }

    public function organization() { return $this->belongsTo(Organization::class); }
    public function parameters() { return $this->hasMany(DeviceTemplateParameter::class); }
    public function metadataDefinitions() { return $this->hasMany(DeviceTemplateMetadataDefinition::class); }
    public function eventDefinitions() { return $this->hasMany(DeviceTemplateEventDefinition::class); }
    public function devices() { return $this->hasMany(Device::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function collaborators() { return $this->hasMany(ResourceCollaborator::class, 'resource_id')->where('resource_type', 'device_template'); }
    public function publicationState() { return $this->hasOne(ResourcePublicationState::class, 'resource_id')->where('resource_type', 'device_template'); }
    public function publicationSubmissions() { return $this->hasMany(ResourcePublicationSubmission::class, 'resource_id')->where('resource_type', 'device_template'); }
    public function provisioningSessions() { return $this->hasMany(ProvisioningSession::class); }
    public function firmwareArtifacts() { return $this->hasMany(FirmwareArtifact::class); }
}
