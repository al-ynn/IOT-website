<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationExecution;
use App\Models\CrashReport;
use App\Models\Device;
use App\Models\FirmwareDeployment;
use App\Models\OperationalEvent;
use App\Models\ProvisioningSession;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Log;

class OperationalEventService
{
    private const MAX_CONTEXT_BYTES = 8192;

    public function automationFailure(Automation $automation, AutomationExecution $execution): ?OperationalEvent
    {
        try {
            $deviceId = $automation->loadMissing('triggers')->triggers->first()?->configuration['deviceId'] ?? null;
            $device = $deviceId ? Device::query()->where('organization_id', $automation->organization_id)->find($deviceId) : null;

            return OperationalEvent::create(['organization_id' => $automation->organization_id, 'device_id' => $device?->id, 'device_bound' => $deviceId !== null, 'automation_id' => $automation->id, 'automation_execution_id' => $execution->id, 'source' => 'automation', 'event_type' => 'automation_execution_failed', 'severity' => 'error', 'title' => 'Automation failed', 'message' => 'Automation execution failed for '.$automation->name.'.', 'context' => $this->bounded(['execution_id' => (string) $execution->id, 'trigger_type' => $execution->trigger_type]), 'occurred_at' => $execution->completed_at ?? now()]);
        } catch (\Throwable $error) {
            Log::warning('Operational event persistence failed.', ['exception' => get_class($error)]);

            return null;
        }
    }

    public function provisioningFailed(ProvisioningSession $session): ?OperationalEvent
    {
        return $this->provisioningEvent($session, 'provisioning_session_failed', 'error', 'Provisioning failed', $session->failure_message ?? 'Provisioning failed.');
    }

    public function provisioningExpired(ProvisioningSession $session): ?OperationalEvent
    {
        return $this->provisioningEvent($session, 'provisioning_session_expired', 'warning', 'Provisioning expired', 'The provisioning session expired before completion.');
    }

    public function firmwareDeliveryUnavailable(FirmwareDeployment $deployment): ?OperationalEvent
    {
        try {
            return OperationalEvent::create(['organization_id' => $deployment->organization_id, 'device_bound' => false, 'firmware_deployment_id' => $deployment->id, 'source' => 'firmware', 'event_type' => 'firmware_delivery_unavailable', 'severity' => 'warning', 'title' => 'Firmware delivery unavailable', 'message' => 'Firmware deployment was recorded but no secure Device delivery transport is configured.', 'context' => $this->bounded(['artifact_id' => (string) $deployment->firmware_artifact_id, 'deployment_id' => (string) $deployment->id, 'failure_code' => 'delivery_not_configured']), 'occurred_at' => now()]);
        } catch (\Throwable $error) {
            Log::warning('Operational event persistence failed.', ['exception' => get_class($error)]);

            return null;
        }
    }

    public function webhookFailure(WebhookDelivery $delivery): ?OperationalEvent
    {
        try {
            $delivery->loadMissing('webhook');

            return OperationalEvent::create(['organization_id' => $delivery->webhook->organization_id, 'device_bound' => false, 'source' => 'webhook', 'event_type' => 'webhook_delivery_failed', 'severity' => 'error', 'title' => 'Webhook delivery failed', 'message' => 'Webhook delivery failed after its final attempt.', 'context' => $this->bounded(['webhook_id' => (string) $delivery->webhook_id, 'delivery_id' => (string) $delivery->id, 'event_type' => $delivery->event_type, 'response_status' => $delivery->response_status, 'error_code' => $delivery->error_code]), 'occurred_at' => now()]);
        } catch (\Throwable $error) {
            Log::warning('Operational event persistence failed.', ['exception' => get_class($error)]);

            return null;
        }
    }

    public function crashReported(CrashReport $report): ?OperationalEvent
    {
        try {
            $report->loadMissing('device');

            return OperationalEvent::create(['organization_id' => $report->organization_id, 'device_id' => $report->device_id, 'device_bound' => true, 'source' => 'device', 'event_type' => 'device_crash_reported', 'severity' => 'error', 'title' => 'Device crash reported', 'message' => 'Crash report received from '.$report->device->name.'.', 'context' => $this->bounded(['crash_report_id' => (string) $report->id, 'crash_type' => $report->crash_type, 'firmware_version' => $report->firmware_version]), 'occurred_at' => $report->received_at]);
        } catch (\Throwable $error) {
            Log::warning('Operational event persistence failed.', ['exception' => get_class($error)]);

            return null;
        }
    }

    public function sanitize(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (preg_match('/token|password|secret|authorization|api[_-]?key|credential/i', (string) $key)) {
                $clean[$key] = '[REDACTED]';

                continue;
            }$clean[$key] = is_array($value) ? $this->sanitize($value) : $this->safeScalar($value);
        }

return $clean;
    }

    public function safeText(?string $value, int $limit = 500): ?string
    {
        return $value === null ? null : (string) $this->safeScalar(mb_substr($value, 0, $limit));
    }

    private function bounded(array $context): array
    {
        $clean = $this->sanitize($context);

        return strlen((string) json_encode($clean)) <= self::MAX_CONTEXT_BYTES ? $clean : ['notice' => 'Context omitted because it exceeded the safe size limit.'];
    }

    private function provisioningEvent(ProvisioningSession $session, string $type, string $severity, string $title, string $message): ?OperationalEvent
    {
        try {
            return OperationalEvent::create(['organization_id' => $session->organization_id, 'device_id' => $session->device_id, 'device_bound' => $session->device_id !== null, 'provisioning_session_id' => $session->id, 'source' => 'provisioning', 'event_type' => $type, 'severity' => $severity, 'title' => $title, 'message' => $this->safeText($message), 'context' => $this->bounded(['session_id' => (string) $session->id, 'failure_code' => $session->failure_code]), 'occurred_at' => now()]);
        } catch (\Throwable $error) {
            Log::warning('Operational event persistence failed.', ['exception' => get_class($error)]);

            return null;
        }
    }

    private function safeScalar(mixed $value): mixed
    {
        if (! is_string($value)) {
            return is_scalar($value) || $value === null ? $value : '[UNSUPPORTED]';
        }$value = preg_replace('/(?:Bearer\s+\S+|(?:token|password|secret|api[_-]?key)\s*[:=]\s*\S+)/i', '[REDACTED]', $value) ?? $value;
        $value = preg_replace('/[A-Za-z]:\\\\[^\r\n\s]*/', '[PATH]', $value) ?? $value;
        $value = preg_replace('#(?<!\w)/(?:[^/\s]+/)+[^\s]*#','[PATH]',$value) ?? $value;

        return mb_substr($value,0,500);
    }
}
