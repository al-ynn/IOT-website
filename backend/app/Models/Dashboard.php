<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dashboard extends Model
{
    public const SCOPES = ['personal', 'admin_global', 'device'];
    protected $fillable = ['organization_id','device_id','owner_user_id','name','description','scope_type','is_default','layout_version','created_by','updated_by','configuration'];
    protected $casts = ['configuration'=>'array','is_default'=>'boolean','layout_version'=>'integer'];
    public function organization() { return $this->belongsTo(Organization::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function device() { return $this->belongsTo(Device::class); }
    public function publicationState() { return $this->hasOne(ResourcePublicationState::class, 'resource_id')->where('resource_type', 'dashboard'); }
    public function widgets() { return $this->hasMany(DashboardWidget::class)->orderBy('position'); }
    public function collaborators() { return $this->hasMany(ResourceCollaborator::class, 'resource_id')->where('resource_type', 'dashboard'); }
    public function mapAssets() { return $this->hasMany(DashboardMapAsset::class); }
}
