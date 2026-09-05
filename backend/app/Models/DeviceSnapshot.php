<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeviceSnapshot extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'device_id', 'name', 'description', 'payload', 'captured_at', 'created_by'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'captured_at' => 'datetime'];
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
