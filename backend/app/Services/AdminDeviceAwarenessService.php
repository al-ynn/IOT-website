<?php

namespace App\Services;

use App\Jobs\NotifyAdminsOfDeviceCreated;
use App\Models\Device;

class AdminDeviceAwarenessService
{
    public function dispatch(Device $device): void
    {
        NotifyAdminsOfDeviceCreated::dispatch($device->id);
    }
}
