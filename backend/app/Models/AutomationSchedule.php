<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class AutomationSchedule extends Model{protected $fillable=['automation_id','type','configuration','enabled','last_run_at','next_run_at'];protected $casts=['configuration'=>'array','enabled'=>'boolean','last_run_at'=>'datetime','next_run_at'=>'datetime'];public function automation(){return $this->belongsTo(Automation::class);}}
