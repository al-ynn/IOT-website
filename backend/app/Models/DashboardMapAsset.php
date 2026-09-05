<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DashboardMapAsset extends Model
{
    protected $fillable = ['dashboard_id', 'uploaded_by', 'disk', 'path', 'mime_type', 'size', 'width', 'height'];
    protected $casts = ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    public function dashboard() { return $this->belongsTo(Dashboard::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
