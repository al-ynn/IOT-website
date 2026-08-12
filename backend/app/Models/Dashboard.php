<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dashboard extends Model
{
    protected $fillable = ['organization_id','name','description','configuration'];
    protected $casts = ['configuration'=>'array'];
    public function organization() { return $this->belongsTo(Organization::class); }
}
