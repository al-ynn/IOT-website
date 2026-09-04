<?php

namespace App\Services;

use App\Collaboration\WebhookCollaborationAuthorizer;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Facades\DB;

final class WebhookService
{
    public function __construct(private WebhookUrlGuard $guard, private SafeResourceSaveService $saves, private WebhookCollaborationAuthorizer $authorizer, private ResourceLifecycleService $lifecycle) {}

    public function create(Organization $org, User $user, array $data): array
    {
        return DB::transaction(function () use ($org, $user, $data) {
            $this->guard->assertSafe($data['url']);
            $secret = $this->secret();
            $webhook = $org->webhooks()->create(['name' => $data['name'], 'url' => $data['url'], 'enabled' => false, 'event_types' => $data['event_types'], 'signing_secret' => $secret, 'secret_prefix' => 'Configured', 'created_by' => $user->id]);
            ResourceCollaborator::create(['resource_type' => 'webhook', 'resource_id' => $webhook->id, 'user_id' => $user->id, 'permission' => 'edit', 'granted_by' => $user->id]);
            app(ResourceRevisionService::class)->recordWebhook($webhook, $user, 'Webhook created');

            return [$webhook, $secret];
        });
    }

    public function update(User $actor, Webhook $webhook, array $data, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): Webhook
    {
        if (isset($data['url'])) {
            $this->guard->assertSafe($data['url']);
        }

        return $this->saves->execute($actor, 'webhook', Webhook::class, $webhook->id, $baseRevisionId, fn (User $user, Webhook $locked) => abort_unless($this->authorizer->canEdit($user, $locked), 404), fn (Webhook $locked) => $this->lifecycle->assertActive('webhook', $locked->id, 'Disabled or Archived Webhooks cannot be edited.'), function (Webhook $locked) use ($data) {
            $locked->update(array_intersect_key($data, array_flip(['name', 'url', 'event_types'])));

            return $locked;
        }, fn (Webhook $locked, User $user) => app(ResourceRevisionService::class)->recordWebhook($locked, $user, 'Webhook configuration updated'), $baseRevisionId !== null, $idempotencyKey, $data);
    }

    public function rotate(Webhook $webhook): array
    {
        $secret = $this->secret();
        $webhook->update(['signing_secret' => $secret, 'secret_prefix' => 'Configured']);

        return [$webhook->refresh(), $secret];
    }

    public function delete(Webhook $webhook): void
    {
        $webhook->delete();
    }

    private function secret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
