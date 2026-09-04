<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class UserMonitoredDevice extends Model{protected $fillable=['user_id','device_id'];public function user(){return $this->belongsTo(User::class);}public function device(){return $this->belongsTo(Device::class);}}
