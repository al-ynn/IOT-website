<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceCredential extends Model
{
    public const SCOPES = ['telemetry:write', 'events:write', 'firmware:read', 'crash:write'];

    protected $fillable = ['device_id', 'public_id', 'name', 'token_prefix', 'token_hash', 'scopes', 'last_used_at', 'expires_at', 'revoked_at', 'created_by'];

    protected $hidden = ['token_hash'];

    protected $casts = ['scopes' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
