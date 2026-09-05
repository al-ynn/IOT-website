<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CrashReportService
{
    private const MAX_CONTEXT_BYTES = 16384;

    private const MAX_DEPTH = 6;

    public function __construct(private OperationalEventService $events) {}

    public function ingest(Device $device, array $data): array
    {
        $context = $this->sanitizeContext($data['context'] ?? []);
        if (strlen((string) json_encode($context)) > self::MAX_CONTEXT_BYTES) {
            throw ValidationException::withMessages(['context' => ['The sanitized crash context may not exceed 16 KiB.']]);
        }
        $attributes = ['organization_id' => $device->organization_id, 'client_report_id' => $data['client_report_id'] ?? null, 'crash_type' => $data['crash_type'], 'reason' => $this->safeText($data['reason'] ?? null, 500), 'message' => $this->safeText($data['message'] ?? null, 1000), 'firmware_version' => $this->safeText($data['firmware_version'] ?? null, 100), 'runtime_version' => $this->safeText($data['runtime_version'] ?? null, 100), 'uptime_seconds' => $data['uptime_seconds'] ?? null, 'reboot_reason' => $this->safeText($data['reboot_reason'] ?? null, 255), 'stack_trace' => $this->safeStack($data['stack_trace'] ?? null), 'context' => $context ?: null, 'reported_at' => $data['reported_at'] ?? null, 'received_at' => now()];
        $report = ! empty($data['client_report_id'])
            ? $device->crashReports()->firstOrCreate(['client_report_id' => $data['client_report_id']], $attributes)
            : $device->crashReports()->create($attributes);
        $created = $report->wasRecentlyCreated;
        if (! $created) {
            return [$report->load(['device:id,name,external_id', 'organization:id,name', 'operationalEvent']), false];
        }
        try {
            $event = $this->events->crashReported($report);
            if ($event) {
                $report->update(['operational_event_id' => $event->id]);
            }
        } catch (\Throwable $error) {
            Log::warning('Crash Operational Event creation failed.', ['crash_report_id' => $report->id, 'exception' => get_class($error)]);
        }

        return [$report->fresh()->load(['device:id,name,external_id', 'organization:id,name', 'operationalEvent']), true];
    }

    public function sanitizeContext(array $value, int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['_truncated' => 'Maximum context depth reached.'];
        }
        $clean = [];
        foreach ($value as $key => $item) {
            $name = (string) $key;
            if (preg_match('/^(authorization|token|access_token|refresh_token|secret|password|credential|api[_-]?key|cookie)$/i', $name)) {
                $clean[$name] = '[REDACTED]';

                continue;
            }
            $clean[$name] = is_array($item) ? $this->sanitizeContext($item, $depth + 1) : $this->safeValue($item);
        }

        return $clean;
    }

    private function safeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return is_scalar($value) || $value === null ? $value : '[UNSUPPORTED]';
        }
        $value = preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace('/\biotd_[0-9a-f-]{36}_[A-Za-z0-9_-]{20,}\b/i', '[REDACTED DEVICE CREDENTIAL]', $value) ?? $value;
        $value = preg_replace('/\bwhsec_[A-Za-z0-9_-]{8,}\b/i', '[REDACTED WEBHOOK SECRET]', $value) ?? $value;

        return mb_substr($value, 0, 1000);
    }

    private function safeText(?string $value, int $limit): ?string
    {
        return $value === null ? null : mb_substr(strip_tags((string) $this->safeValue($value)), 0, $limit);
    }

    private function safeStack(?string $value): ?string
    {
        return $value === null ? null : mb_substr(strip_tags((string) $this->safeValue($value)), 0, 16000);
    }
}
