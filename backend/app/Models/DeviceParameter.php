<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class DeviceParameter extends Model{protected $fillable=['device_id','name','key','data_type','unit','description','semantic','configuration'];protected $casts=['configuration'=>'array'];public function device(){return $this->belongsTo(Device::class);}}
