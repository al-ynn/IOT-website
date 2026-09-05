<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class AutomationExecutionLog extends Model{protected $fillable=['automation_execution_id','automation_action_id','level','message','context','executed_at'];protected $casts=['context'=>'array','executed_at'=>'datetime'];public function execution(){return $this->belongsTo(AutomationExecution::class,'automation_execution_id');}}
