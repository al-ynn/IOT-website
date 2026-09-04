<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\UserMonitoredDevice;

class GlobalDeviceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'device' => [...(new GlobalDeviceResource($this->resource))->resolve($request),'isMonitored'=>UserMonitoredDevice::where('user_id',$request->user()->id)->where('device_id',$this->id)->exists()],
            'assignments' => $this->accessAssignments->map(fn ($assignment) => [
                'id' => (string) $assignment->id,
                'accessLevel' => $assignment->access_level,
                'staff' => [
                    'id' => (string) $assignment->user_id,
                    'name' => $assignment->user?->name ?? '',
                    'email' => $assignment->user?->email ?? '',
                ],
            ])->values(),
        ];
    }
}
