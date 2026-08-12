<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class AutomationCondition extends Model{protected $fillable=['automation_id','logic','position','configuration'];protected $casts=['configuration'=>'array'];public function automation(){return $this->belongsTo(Automation::class);}}
