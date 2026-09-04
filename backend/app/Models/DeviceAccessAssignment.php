<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceAccessAssignment extends Model
{
    protected $fillable = ['device_id', 'user_id', 'access_level', 'assigned_by'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
