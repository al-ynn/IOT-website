<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class AutomationTrigger extends Model{protected $fillable=['automation_id','type','configuration'];protected $casts=['configuration'=>'array'];public function automation(){return $this->belongsTo(Automation::class);}}
