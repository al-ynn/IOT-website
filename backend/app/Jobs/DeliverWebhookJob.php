<?php

namespace App\Jobs;

use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\WebhookDelivery;
use App\Services\OperationalEventService;
use App\Services\ResourceLifecycleService;
use App\Services\SystemSettingsService;
use App\Services\WebhookUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public int $timeout = 75;

    public bool $failOnTimeout = true;

    public array $backoff = [60, 300, 900, 3600];

    public int $lifecycleGeneration;

    public function __construct(public string $deliveryId, ?int $lifecycleGeneration = null)
    {
        $webhookId = WebhookDelivery::query()->whereKey($deliveryId)->value('webhook_id');
        $this->lifecycleGeneration = $lifecycleGeneration ?? ($webhookId ? app(ResourceLifecycleService::class)->generation('webhook', $webhookId) : 1);
    }

    public function handle(WebhookUrlGuard $guard, OperationalEventService $events, ?SystemSettingsService $settings = null): void
    {
        $settings ??= app(SystemSettingsService::class);
        $delivery = WebhookDelivery::with(['webhook.organization'])->findOrFail($this->deliveryId);
        $webhook = $delivery->webhook;
        $lifecycle = app(ResourceLifecycleService::class);
        if ($webhook && $lifecycle->state('webhook', $webhook->id) !== 'active') {
            $delivery->update(['status' => 'cancelled', 'error_code' => 'webhook_lifecycle_inactive', 'error_message' => 'Webhook resource is disabled or archived.']);

            return;
        }
        if ($webhook && ! $lifecycle->allowsGeneration('webhook', $webhook->id, $this->lifecycleGeneration)) {
            $delivery->update(['status' => 'cancelled', 'error_code' => 'webhook_lifecycle_stale', 'error_message' => 'Webhook lifecycle changed after this delivery was queued.']);

            return;
        }
        if (! $webhook || $webhook->trashed() || ! $webhook->enabled) {
            $delivery->update(['status' => 'cancelled', 'error_code' => 'webhook_disabled', 'error_message' => 'Webhook is disabled or unavailable.']);

            return;
        }

        $revision = ResourceRevision::whereKey($delivery->webhook_revision_id)->where(['resource_type' => 'webhook', 'resource_id' => $webhook->id])->first();
        $endpoint = $revision?->snapshot['configuration']['url'] ?? null;
        $activation = ResourcePublicationSubmission::whereKey($delivery->activation_submission_id)->where(['resource_type' => 'webhook', 'resource_id' => $webhook->id, 'submitted_revision_id' => $delivery->webhook_revision_id, 'status' => 'approved'])->exists();
        if (! $revision || ! $endpoint || ! $activation) {
            $delivery->update(['status' => 'cancelled', 'error_code' => 'activation_provenance_invalid', 'error_message' => 'Webhook activation provenance is no longer valid.']);

            return;
        }
        $attempt = $delivery->attempt_count + 1;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $webhook->signing_secret);
        try {
            $guard->assertSafe($endpoint);
            $response = Http::withOptions(['allow_redirects' => false])->connectTimeout($settings->integer('webhook_connect_timeout_seconds'))->timeout($settings->integer('webhook_request_timeout_seconds'))->withHeaders(['X-IoT-Signature' => 'sha256='.$signature, 'X-IoT-Timestamp' => $timestamp, 'X-IoT-Delivery-Id' => $delivery->id, 'User-Agent' => 'IoT-Platform-Webhooks/1.0'])->withBody($body, 'application/json')->post($endpoint);
            $status = $response->status();
            $excerpt = $this->excerpt($response->body());
            if ($status >= 200 && $status < 300) {
                $delivery->update(['status' => 'delivered', 'attempt_count' => $attempt, 'response_status' => $status, 'response_excerpt' => $excerpt, 'attempted_at' => now(), 'next_retry_at' => null, 'delivered_at' => now(), 'error_code' => null, 'error_message' => null]);
                $webhook->update(['last_delivery_at' => now()]);

                return;
            }
            $this->failure($delivery, $attempt, 'http_'.$status, 'Webhook receiver returned HTTP '.$status.'.', $status, $excerpt, $status === 408 || $status === 429 || $status >= 500, $events, $settings);
        } catch (ValidationException) {
            $this->failure($delivery, $attempt, 'unsafe_destination', 'Webhook destination failed security validation.', null, null, false, $events, $settings);
        } catch (\Throwable $error) {
            if ($delivery->refresh()->status === 'retrying') {
                throw $error;
            }
            $this->failure($delivery, $attempt, 'network_error', 'Webhook delivery failed because the receiver was unavailable.', null, null, true, $events, $settings);
        }
    }

    private function failure(WebhookDelivery $delivery, int $attempt, string $code, string $message, ?int $status, ?string $excerpt, bool $retryable, OperationalEventService $events, SystemSettingsService $settings): void
    {
        $final = ! $retryable || $attempt >= $settings->integer('webhook_max_attempts');
        $delivery->update(['status' => $final ? 'failed' : 'retrying', 'attempt_count' => $attempt, 'response_status' => $status, 'response_excerpt' => $excerpt, 'error_code' => $code, 'error_message' => $message, 'attempted_at' => now(), 'next_retry_at' => $final ? null : now()->addSeconds($this->backoff[min($attempt - 1, count($this->backoff) - 1)])]);
        if ($final) {
            $events->webhookFailure($delivery);

            return;
        }throw new \RuntimeException($message);
    }

    private function excerpt(string $body): string
    {
        $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $body) ?? '';

        return mb_substr($value, 0, (int) config('webhooks.response_excerpt_bytes'));
    }

    public function failed(\Throwable $error): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);
        if ($delivery && ! in_array($delivery->status, ['delivered', 'failed', 'cancelled'], true)) {
            $delivery->update(['status' => 'failed', 'error_code' => 'job_failed', 'error_message' => 'Webhook delivery worker failed before completion.', 'attempted_at' => now(), 'next_retry_at' => null]);
            app(OperationalEventService::class)->webhookFailure($delivery);
        }
        report($error);
    }
}
