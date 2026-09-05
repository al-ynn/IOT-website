<?php

namespace App\Services;

class SystemSettingDefinitionRegistry
{
    public function all(): array
    {
        return [
            'telemetry_retention_days' => $this->integer('Data Retention', 90, 1, 3650, 'days', 'Telemetry records older than this become eligible for scheduled cleanup.'),
            'operational_event_retention_days' => $this->integer('Data Retention', 180, 1, 3650, 'days', 'Operational Events older than this become eligible for scheduled cleanup.'),
            'crash_report_retention_days' => $this->integer('Data Retention', 365, 1, 3650, 'days', 'Crash Reports older than this become eligible for scheduled cleanup.'),
            'report_artifact_retention_days' => $this->integer('Data Retention', 90, 1, 3650, 'days', 'Generated report files expire after this period; run metadata is preserved.'),
            'provisioning_session_expiry_minutes' => $this->integer('Provisioning', 30, 5, 1440, 'minutes', 'Lifetime assigned to newly created provisioning sessions.'),
            'webhook_max_attempts' => $this->integer('Webhooks', 5, 1, 10, 'attempts', 'Maximum bounded delivery attempts before a Webhook delivery fails.'),
            'webhook_connect_timeout_seconds' => $this->integer('Webhooks', 3, 1, 15, 'seconds', 'Maximum time allowed to establish an outbound Webhook connection.'),
            'webhook_request_timeout_seconds' => $this->integer('Webhooks', 10, 2, 60, 'seconds', 'Maximum total time allowed for an outbound Webhook request.'),
            'report_max_date_range_days' => $this->integer('Reports', 31, 1, 90, 'days', 'Largest date range accepted by report definitions and runs.'),
            'firmware_max_upload_mb' => $this->integer('Firmware', 10, 1, 500, 'MiB', 'Maximum accepted Firmware Artifact upload size.'),
            'crash_payload_max_kb' => $this->integer('Crash Reports', 64, 16, 256, 'KiB', 'Maximum JSON request body accepted by Device crash ingestion.'),
        ];
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    private function integer(string $category, int $default, int $min, int $max, string $unit, string $description): array
    {
        return compact('category', 'default', 'min', 'max', 'unit', 'description') + ['type' => 'integer'];
    }
}
