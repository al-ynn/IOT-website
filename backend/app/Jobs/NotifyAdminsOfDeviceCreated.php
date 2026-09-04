<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\User;
use App\Services\NotificationMaterializationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyAdminsOfDeviceCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(public readonly int $deviceId)
    {
        $this->afterCommit();
    }

    public function handle(?NotificationMaterializationService $notifications = null): void
    {
        $notifications ??= app(NotificationMaterializationService::class);
        $device = Device::with(['organization:id,name', 'creator:id,name'])->find($this->deviceId);
        if (! $device || ! $device->creator || $device->creator->isPlatformAdmin()) {
            return;
        }

        User::query()->where('platform_role', 'platform_admin')->where('organization_id', $device->organization_id)->where('status', 'active')->each(function (User $recipient) use ($device, $notifications) {
            $notifications->createOnce($recipient, 'device.created', "device:{$device->id}:created", [
                'organization_id' => $device->organization_id,
                'actor_id' => $device->created_by,
                'resource_type' => 'device',
                'resource_id' => $device->id,
                'action_url' => "/admin/devices/{$device->id}",
                'data' => ['device_name' => $device->name, 'organization_name' => $device->organization?->name],
                'title' => 'Device created',
                'message' => "{$device->creator->name} created {$device->name}.",
                'severity' => 'info',
                'requires_action' => false,
            ]);
        });
    }
}
