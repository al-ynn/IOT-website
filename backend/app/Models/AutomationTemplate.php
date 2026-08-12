<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class AutomationTemplate extends Model{protected $fillable=['organization_id','name','description','category','definition','active','created_by'];protected $casts=['definition'=>'array','active'=>'boolean'];}
