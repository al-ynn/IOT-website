<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class AutomationAction extends Model{protected $fillable=['automation_id','type','position','continue_on_failure','configuration'];protected $casts=['configuration'=>'array','continue_on_failure'=>'boolean'];public function automation(){return $this->belongsTo(Automation::class);}}
