<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ProvisioningSession;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProvisioningSessionService
{
    public function __construct(
        private DeviceAccessService $deviceAccess,
        private DeviceTemplateService $templates,
        private DeviceParameterService $parameters,
        private OperationalEventService $events,
        private SystemSettingsService $settings,
    ) {}

    public function create(Organization $organization, User $initiator, array $data): ProvisioningSession
    {
        $template = isset($data['device_template_id']) ? $organization->deviceTemplates()->findOrFail($data['device_template_id']) : null;
        if ($template) {
            app(ResourceLifecycleService::class)->assertActive('device_template', $template->id, 'Restore the Template before provisioning from it.');
        }
        $now = now();

        return $organization->provisioningSessions()->create([
            'initiated_by' => $initiator->id,
            'device_template_id' => $template?->id,
            'name' => $data['name'] ?? null,
            'status' => 'pending',
            'started_at' => $now,
            'expires_at' => $now->copy()->addMinutes($this->settings->integer('provisioning_session_expiry_minutes')),
        ]);
    }

    public function complete(ProvisioningSession $session, array $deviceData): ProvisioningSession
    {
        $expired = false;
        $result = DB::transaction(function () use ($session, $deviceData, &$expired) {
            $locked = ProvisioningSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->status === 'pending' && $locked->expires_at->isPast()) {
                $locked->update(['status' => 'expired', 'failed_at' => now(), 'failure_code' => 'session_expired', 'failure_message' => 'The provisioning session expired before completion.']);
                $expired = true;

                return $locked;
            }
            $this->assertPending($locked);
            if ($locked->template) {
                app(ResourceLifecycleService::class)->assertActive('device_template', $locked->template->id, 'Restore the Template before completing provisioning.');
            }

            $device = $locked->organization->devices()->create([
                'name' => $deviceData['name'],
                'type' => $deviceData['type'],
                'external_id' => $deviceData['serialNumber'],
                'protocol' => $deviceData['protocol'],
                'mac_address' => $deviceData['macAddress'] ?? null,
            ]);
            if ($locked->template) {
                $this->templates->apply($locked->template, $device, $this->parameters);
            }
            $initiator = $locked->initiator;
            if ($initiator && ! $initiator->isPlatformAdmin() && $initiator->organization_id === $device->organization_id) {
                $this->deviceAccess->createForCreator($initiator, $device);
            }
            $locked->update(['device_id' => $device->id, 'status' => 'completed', 'completed_at' => now(), 'failure_code' => null, 'failure_message' => null]);

            return $locked;
        });
        if ($expired) {
            $this->events->provisioningExpired($result);
            throw ValidationException::withMessages(['status' => ['This provisioning session has expired.']]);
        }

        return $this->detail($result);
    }

    public function cancel(ProvisioningSession $session): ProvisioningSession
    {
        $this->expireIfDue($session);
        $this->assertPending($session);
        $session->update(['status' => 'cancelled']);

        return $this->detail($session);
    }

    public function fail(ProvisioningSession $session, string $code, string $message): ProvisioningSession
    {
        $this->expireIfDue($session);
        $this->assertPending($session);
        $session->update(['status' => 'failed', 'failed_at' => now(), 'failure_code' => $code, 'failure_message' => $this->events->safeText($message)]);
        $this->events->provisioningFailed($session->refresh());

        return $this->detail($session);
    }

    public function expireDue(): int
    {
        $sessions = ProvisioningSession::query()->where('status', 'pending')->where('expires_at', '<=', now())->get();
        foreach ($sessions as $session) {
            $this->expire($session);
        }

        return $sessions->count();
    }

    public function detail(ProvisioningSession $session): ProvisioningSession
    {
        $this->expireIfDue($session);

        return $session->refresh()->load(['organization:id,name', 'initiator:id,name,email', 'device:id,name,external_id', 'template:id,name']);
    }

    private function expireIfDue(ProvisioningSession $session): void
    {
        if ($session->status === 'pending' && $session->expires_at->isPast()) {
            $this->expire($session);
        }
    }

    private function expire(ProvisioningSession $session): void
    {
        $changed = ProvisioningSession::query()->whereKey($session->id)->where('status', 'pending')->update(['status' => 'expired', 'failed_at' => now(), 'failure_code' => 'session_expired', 'failure_message' => 'The provisioning session expired before completion.', 'updated_at' => now()]);
        if ($changed) {
            $session->refresh();
            $this->events->provisioningExpired($session);
        }
    }

    private function assertPending(ProvisioningSession $session): void
    {
        if ($session->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ["A {$session->status} provisioning session cannot transition again."]]);
        }
    }
}
