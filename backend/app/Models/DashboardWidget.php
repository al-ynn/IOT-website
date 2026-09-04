<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'dashboard_id', 'widget_type', 'title', 'layout', 'configuration', 'position'];
    protected $casts = ['layout' => 'array', 'configuration' => 'array'];
    public function dashboard() { return $this->belongsTo(Dashboard::class); }
}
