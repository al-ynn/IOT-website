<?php

namespace Tests\Feature;

use App\Contracts\DnsResolver;
use App\Jobs\DeliverWebhookJob;
use App\Models\OperationalEvent;
use App\Models\Organization;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\OperationalEventService;
use App\Services\WebhookUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private array $dns = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['webhooks.allow_http' => true]);
        $this->app->instance(DnsResolver::class, new class($this->dns) implements DnsResolver
        {
            public function __construct(public array &$map) {}

            public function resolve(string $host): array
            {
                return filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->map[$host] ?? []);
            }
        });
    }

    private function org(string $slug): Organization
    {
        return Organization::create(['name' => $slug, 'slug' => $slug]);
    }

    private function user(Organization $org, string $role = 'owner'): User
    {
        return User::factory()->create(['organization_id' => $org->id, 'role' => $role, 'status' => 'active']);
    }

    private function create(User $user, array $extra = []): array
    {
        $this->dns['hooks.example.com'] = ['93.184.216.34'];

        $data = $this->actingAs($user)->postJson('/api/webhooks', ['name' => 'Operations', 'url' => 'https://hooks.example.com/iot', 'event_types' => ['automation.failed'], ...$extra])->assertCreated()->json('data');
        $webhook = Webhook::findOrFail($data['id']);
        $revision = \App\Models\ResourceRevision::where(['resource_type'=>'webhook','resource_id'=>$webhook->id])->latest('revision_number')->firstOrFail();
        $submission = \App\Models\ResourcePublicationSubmission::create(['resource_type'=>'webhook','resource_id'=>$webhook->id,'submitted_revision_id'=>$revision->id,'submitted_by'=>$user->id,'status'=>'approved','submitted_at'=>now(),'decision_by'=>$user->id,'decision_at'=>now()]);
        $webhook->update(['approved_revision_id'=>$revision->id,'approved_submission_id'=>$submission->id,'enabled'=>true]);
        return $data;
    }

    public function test_management_is_authenticated_permission_and_tenant_scoped(): void
    {
        $a = $this->org('hooks-a');
        $b = $this->org('hooks-b');
        $owner = $this->user($a);
        $staff = $this->user($a, 'staff');
        $foreign = $this->user($b);
        $this->getJson('/api/webhooks')->assertUnauthorized();
        $this->actingAs($staff)->postJson('/api/webhooks', ['name' => 'No', 'url' => 'https://hooks.example.com', 'event_types' => ['automation.failed']])->assertForbidden();
        $created = $this->create($owner, ['organization_id' => $b->id, 'created_by' => $foreign->id]);
        $row = Webhook::findOrFail($created['id']);
        $this->assertSame($a->id, $row->organization_id);
        $this->assertSame($owner->id, $row->created_by);
        $this->actingAs($foreign)->getJson('/api/webhooks/'.$row->id)->assertNotFound();
        $this->actingAs($foreign)->patchJson('/api/webhooks/'.$row->id, ['name' => 'Attack'])->assertNotFound();
        $this->actingAs($foreign)->deleteJson('/api/webhooks/'.$row->id)->assertNotFound();
    }

    public function test_secret_is_generated_encrypted_revealed_once_and_rotated(): void
    {
        $org = $this->org('hooks-secret');
        $user = $this->user($org);
        $created = $this->create($user, ['secret' => 'attack']);
        $this->assertNotEmpty($created['secret']);
        $raw = DB::table('webhooks')->value('signing_secret');
        $this->assertNotSame($created['secret'], $raw);
        $this->assertStringNotContainsString($created['secret'], $raw);
        $this->actingAs($user)->getJson('/api/webhooks')->assertOk()->assertJsonMissingPath('data.0.secret')->assertJsonMissingPath('data.0.signing_secret');
        $this->actingAs($user)->getJson('/api/webhooks/'.$created['id'])->assertOk()->assertJsonMissingPath('data.secret');
        $rotated = $this->actingAs($user)->postJson('/api/webhooks/'.$created['id'].'/rotate-secret')->assertOk()->json('data.secret');
        $this->assertNotSame($created['secret'], $rotated);
    }

    public function test_ssrf_unsafe_schemes_addresses_userinfo_ports_and_private_dns_are_rejected(): void
    {
        $org = $this->org('hooks-ssrf');
        $user = $this->user($org);
        $this->dns['private.example'] = ['10.0.0.2'];
        foreach (['http://127.0.0.1/x', 'http://[::1]/x', 'http://169.254.169.254/latest', 'https://user:pass@hooks.example.com/x', 'https://hooks.example.com:8443/x', 'file:///etc/passwd', 'gopher://hooks.example.com/x', 'https://private.example/x'] as $url) {
            $this->actingAs($user)->postJson('/api/webhooks', ['name' => 'Unsafe', 'url' => $url, 'event_types' => ['automation.failed']])->assertUnprocessable();
        }$this->assertDatabaseCount('webhooks', 0);
    }

    public function test_delivery_is_queued_signed_and_persisted_without_redirect_following(): void
    {
        $org = $this->org('hooks-delivery');
        $user = $this->user($org);
        Queue::fake();
        $created = $this->create($user);
        $webhook = Webhook::findOrFail($created['id']);
        $response = $this->actingAs($user)->postJson('/api/webhooks/'.$webhook->id.'/test')->assertAccepted();
        Queue::assertPushed(DeliverWebhookJob::class);
        $delivery = WebhookDelivery::findOrFail($response->json('data.deliveryId'));
        Http::fake(['*' => Http::response('accepted', 202)]);
        $job = new DeliverWebhookJob($delivery->id);
        $job->handle(app(WebhookUrlGuard::class), app(OperationalEventService::class));
        $delivery->refresh();
        $this->assertSame('delivered', $delivery->status);
        Http::assertSent(function ($request) use ($delivery, $created) {
            $timestamp = $request->header('X-IoT-Timestamp')[0];
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $created['secret']);

            return $request->method() === 'POST' && $request->header('X-IoT-Delivery-Id')[0] === $delivery->id && hash_equals($expected, $request->header('X-IoT-Signature')[0]);
        });
    }

    public function test_delivery_revalidates_dns_bounds_response_and_records_final_failure_without_recursion(): void
    {
        $org = $this->org('hooks-rebind');
        $user = $this->user($org);
        Queue::fake();
        $created = $this->create($user);
        $webhook = Webhook::findOrFail($created['id']);
        $delivery = $webhook->deliveries()->create(['webhook_revision_id' => $webhook->approved_revision_id, 'activation_submission_id' => $webhook->approved_submission_id, 'event_uuid' => (string) str()->uuid(), 'event_type' => 'webhook.test', 'payload' => ['safe' => true], 'status' => 'pending', 'attempt_count' => 4]);
        $this->dns['hooks.example.com'] = ['10.0.0.1'];
        (new DeliverWebhookJob($delivery->id))->handle(app(WebhookUrlGuard::class), app(OperationalEventService::class));
        $this->assertSame('failed', $delivery->refresh()->status);
        $this->assertSame('unsafe_destination', $delivery->error_code);
        $this->assertDatabaseHas('operational_events', ['source' => 'webhook', 'event_type' => 'webhook_delivery_failed']);
        $this->assertDatabaseCount('webhook_deliveries', 1);
        $this->dns['hooks.example.com'] = ['93.184.216.34'];
        $other = $webhook->deliveries()->create(['webhook_revision_id' => $webhook->approved_revision_id, 'activation_submission_id' => $webhook->approved_submission_id, 'event_uuid' => (string) str()->uuid(), 'event_type' => 'webhook.test', 'payload' => ['safe' => true], 'status' => 'pending']);
        Http::fake(['*' => Http::response(str_repeat('x', 5000), 400, ['Location' => 'http://127.0.0.1'])]);
        (new DeliverWebhookJob($other->id))->handle(app(WebhookUrlGuard::class), app(OperationalEventService::class));
        $this->assertSame('failed', $other->refresh()->status);
        $this->assertLessThanOrEqual(2048, strlen($other->response_excerpt));
        Http::assertSentCount(1);
    }

    public function test_subscriptions_disabled_state_and_manual_retry_are_enforced(): void
    {
        $org = $this->org('hooks-events');
        $user = $this->user($org);
        Queue::fake();
        $created = $this->create($user);
        $webhook = Webhook::findOrFail($created['id']);
        OperationalEvent::create(['organization_id' => $org->id, 'source' => 'automation', 'event_type' => 'automation_execution_failed', 'severity' => 'error', 'message' => 'Safe failure', 'occurred_at' => now()]);
        $this->assertDatabaseCount('webhook_deliveries', 1);
        $webhook->update(['enabled' => false]);
        OperationalEvent::create(['organization_id' => $org->id, 'source' => 'automation', 'event_type' => 'automation_execution_failed', 'severity' => 'error', 'message' => 'Another failure', 'occurred_at' => now()]);
        $this->assertDatabaseCount('webhook_deliveries', 1);
        $delivery = $webhook->deliveries()->first();
        $delivery->update(['status' => 'failed']);
        $this->actingAs($user)->postJson("/api/webhooks/{$webhook->id}/deliveries/{$delivery->id}/retry")->assertUnprocessable();
        $this->assertSame('failed', $delivery->refresh()->status);
    }

    public function test_worker_level_failure_cannot_leave_delivery_pending(): void
    {
        $org = $this->org('hooks-worker-failure');
        $user = $this->user($org);
        Queue::fake();
        $created = $this->create($user);
        $webhook = Webhook::findOrFail($created['id']);
        $delivery = $webhook->deliveries()->create(['webhook_revision_id' => $webhook->approved_revision_id, 'activation_submission_id' => $webhook->approved_submission_id, 'event_uuid' => (string) str()->uuid(), 'event_type' => 'webhook.test', 'payload' => ['safe' => true], 'status' => 'pending']);
        $job = new DeliverWebhookJob($delivery->id);
        $job->failed(new \RuntimeException('worker stopped'));
        $this->assertSame('failed', $delivery->refresh()->status);
        $this->assertSame('job_failed',$delivery->error_code);
        $this->assertNull($delivery->next_retry_at);
        $this->assertDatabaseHas('operational_events',['source' => 'webhook', 'event_type' => 'webhook_delivery_failed']);
    }
}
