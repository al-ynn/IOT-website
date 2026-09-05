<?php

namespace App\Services;

use App\Jobs\ProcessNotificationOutboxEvent;
use App\Models\Device;
use App\Models\NotificationOutboxEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class NotificationOutboxService
{
    public const DEVICE_CREATED = 'device.created';
    public const MATERIALIZE = 'notification.materialize';

    public function recordDeviceCreated(Device $device): NotificationOutboxEvent
    {
        return $this->record(self::DEVICE_CREATED, "device:{$device->id}:created", 'device', $device->id);
    }

    public function recordNotification(User $recipient, string $ruleKey, string $factIdentity, array $attributes, bool $explicitReminder = false): NotificationOutboxEvent
    {
        $identity = hash('sha256', "$ruleKey|$factIdentity|{$recipient->id}");
        return $this->record(self::MATERIALIZE, $identity, $attributes['resource_type'] ?? 'notification', $attributes['resource_id'] ?? $recipient->id, [
            'recipient_id' => $recipient->id,
            'rule_key' => $ruleKey,
            'fact_identity' => $factIdentity,
            'attributes' => $attributes,
            'explicit_reminder' => $explicitReminder,
        ]);
    }

    public function process(NotificationOutboxEvent $event): void
    {
        $event = $event->fresh();
        if (! $event || $event->processed_at || $event->failed_at) {
            return;
        }

        $event->increment('attempts');
        DB::transaction(function () use ($event) {
            $event = NotificationOutboxEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($event->processed_at || $event->failed_at) return;
            match ($event->event_type) {
                self::DEVICE_CREATED => $this->deviceCreated($event),
                self::MATERIALIZE => $this->materialize($event),
                default => throw new \LogicException('Unsupported Notification outbox event.'),
            };
            $event->update(['processed_at' => now(), 'last_error_code' => null]);
        });
    }

    public function markRetry(NotificationOutboxEvent $event, \Throwable $error): void
    {
        $attempts = (int) $event->refresh()->attempts;
        $event->update([
            'available_at' => now()->addSeconds(match (true) { $attempts < 2 => 30, $attempts < 3 => 120, default => 600 }),
            'failed_at' => $attempts >= 5 ? now() : null,
            'last_error_code' => class_basename($error),
        ]);
    }

    private function record(string $eventType, string $factIdentity, string $aggregateType, int|string $aggregateId, ?array $payload = null): NotificationOutboxEvent
    {
        $event = NotificationOutboxEvent::firstOrCreate([
            'event_type' => $eventType,
            'fact_identity' => $factIdentity,
        ], [
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'payload' => $payload,
            'available_at' => now(),
        ]);
        if ($event->wasRecentlyCreated) ProcessNotificationOutboxEvent::dispatch($event->id)->afterCommit();
        return $event;
    }

    private function materialize(NotificationOutboxEvent $event): void
    {
        $payload = $event->payload ?? [];
        $recipient = User::query()->whereKey($payload['recipient_id'] ?? null)->where('status', 'active')->first();
        if (! $recipient) return;
        app(NotificationMaterializationService::class)->createOnce(
            $recipient,
            (string) ($payload['rule_key'] ?? ''),
            (string) ($payload['fact_identity'] ?? ''),
            (array) ($payload['attributes'] ?? []),
            (bool) ($payload['explicit_reminder'] ?? false),
        );
    }

    private function deviceCreated(NotificationOutboxEvent $event): void
    {
        $device = Device::with(['organization:id,name', 'creator:id,name'])->find($event->aggregate_id);
        if (! $device || ! $device->creator || $device->creator->isPlatformAdmin()) return;
        $service = app(NotificationMaterializationService::class);
        User::where('platform_role', 'platform_admin')->where('organization_id', $device->organization_id)->where('status', 'active')->each(fn (User $recipient) => $service->createOnce($recipient, 'device.created', $event->fact_identity, [
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
        ]));
    }
}
