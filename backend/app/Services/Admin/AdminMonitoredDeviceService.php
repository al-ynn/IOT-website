<?php

namespace App\Services\Admin;

use App\Models\Device;
use App\Models\User;
use App\Models\UserMonitoredDevice;

class AdminMonitoredDeviceService
{
    public const DISPLAY_LIMIT = 100;

    public function overview(User $admin): array
    {
        $query = $admin->monitoredDevices();
        $total = (clone $query)->count();
        $online = (clone $query)->where('devices.status', 'online')->count();
        $devices = $query->with('organization:id,name')->withCount('accessAssignments')->orderByRaw("CASE WHEN devices.status = 'online' THEN 0 ELSE 1 END")->orderBy('devices.name')->limit(self::DISPLAY_LIMIT)->get();

        return ['summary' => ['total' => $total, 'online' => $online, 'offline' => $total - $online], 'devices' => $devices];
    }

    public function add(User $admin, Device $device): void
    {
        abort_unless((int) $admin->organization_id === (int) $device->organization_id, 404);
        UserMonitoredDevice::firstOrCreate(['user_id' => $admin->id, 'device_id' => $device->id]);
    }

    public function remove(User $admin, Device $device): void
    {
        abort_unless((int) $admin->organization_id === (int) $device->organization_id, 404);
        UserMonitoredDevice::where('user_id', $admin->id)->where('device_id', $device->id)->delete();
    }

    public function isMonitored(User $admin, Device $device): bool
    {
        return UserMonitoredDevice::where('user_id', $admin->id)->where('device_id', $device->id)->exists();
    }
}
