<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FirmwareDeploymentDevice extends Model { protected $fillable=['firmware_deployment_id','device_id','status','attempted_at','failure_code','failure_message'];protected $casts=['attempted_at'=>'datetime'];public function deployment(){return $this->belongsTo(FirmwareDeployment::class,'firmware_deployment_id');}public function device(){return $this->belongsTo(Device::class);} }
