<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class DeviceTemplateParameter extends Model{protected $fillable=['device_template_id','name','key','data_type','unit','description','semantic','configuration'];protected $casts=['configuration'=>'array'];public function template(){return $this->belongsTo(DeviceTemplate::class,'device_template_id');}}
