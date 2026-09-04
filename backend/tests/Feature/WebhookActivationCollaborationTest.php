<?php

namespace Tests\Feature;

use App\Contracts\DnsResolver;
use App\Jobs\DeliverWebhookJob;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WebhookActivationCollaborationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(DnsResolver::class, new class implements DnsResolver
        {
            public function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    private function user(Organization $o, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $o->id, 'role' => 'owner', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    public function test_private_access_safe_revision_and_exact_activation_approval(): void
    {
        $o = Organization::create(['name' => 'Org', 'slug' => 'webhook-phase59']);
        $editor = $this->user($o);
        $other = $this->user($o);
        $admin = $this->user($o, true);
        $created = $this->actingAs($editor)->postJson('/api/webhooks', ['name' => 'Ops', 'url' => 'https://hooks.example.com/v1', 'event_types' => ['automation.failed']])->assertCreated()->assertJsonPath('data.enabled', false)->json('data');
        $w = Webhook::findOrFail($created['id']);
        $this->assertDatabaseHas('resource_collaborators', ['resource_type' => 'webhook', 'resource_id' => $w->id, 'user_id' => $editor->id, 'permission' => 'edit']);
        $this->actingAs($other)->getJson("/api/webhooks/$w->id")->assertNotFound();
        $revision = ResourceRevision::where(['resource_type' => 'webhook', 'resource_id' => $w->id])->firstOrFail();
        $encoded = json_encode($revision->snapshot);
        $this->assertStringNotContainsString($created['secret'], $encoded);
        $submission = $this->actingAs($editor)->postJson("/api/webhooks/$w->id/activation-submissions", ['revision_id' => $revision->id])->assertCreated()->json('data.id');
        $this->actingAs($admin)->getJson("/api/admin/webhook-activation-submissions/$submission/history")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/webhook-activation-submissions/$submission/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/webhook-activation-submissions/$submission/approve")->assertOk();
        $this->assertFalse($w->fresh()->enabled);
        $this->assertSame($revision->id, $w->fresh()->approved_revision_id);
        $this->actingAs($editor)->postJson("/api/webhooks/$w->id/activate")->assertOk();
        Queue::fake();
        $run = $this->actingAs($editor)->postJson("/api/webhooks/$w->id/test")->assertAccepted()->json('data.deliveryId');
        $delivery = WebhookDelivery::findOrFail($run);
        $this->assertSame($revision->id, $delivery->webhook_revision_id);
        $this->assertSame((int) $submission, $delivery->activation_submission_id);
        $this->actingAs($editor)->patchJson("/api/webhooks/$w->id", ['url' => 'https://hooks.example.com/v2'])->assertOk();
        $this->assertSame($revision->id, $delivery->fresh()->webhook_revision_id);
        $this->assertSame($revision->id, $w->fresh()->approved_revision_id);
        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_view_edit_permissions_do_not_reveal_or_rotate_secret(): void
    {
        $o = Organization::create(['name' => 'Org', 'slug' => 'webhook-view']);
        $editor = $this->user($o);
        $viewer = $this->user($o);
        $created = $this->actingAs($editor)->postJson('/api/webhooks', ['name' => 'Ops', 'url' => 'https://hooks.example.com/v1', 'event_types' => ['automation.failed']])->assertCreated()->json('data');
        ResourceCollaborator::create(['resource_type' => 'webhook', 'resource_id' => $created['id'], 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $editor->id]);
        $this->actingAs($viewer)->getJson('/api/webhooks/'.$created['id'])->assertOk()->assertJsonMissingPath('data.secret');
        $this->actingAs($viewer)->patchJson('/api/webhooks/'.$created['id'], ['name' => 'No'])->assertNotFound();
        $this->actingAs($viewer)->postJson('/api/webhooks/'.$created['id'].'/rotate-secret')->assertNotFound();
    }

    public function test_foreign_admin_cannot_read_or_decide_webhook_review_even_with_corrupt_claim(): void
    {
        $orgA = Organization::create(['name' => 'A', 'slug' => 'webhook-a']);
        $orgB = Organization::create(['name' => 'B', 'slug' => 'webhook-b']);
        $adminA = $this->user($orgA, true);
        $editorB = $this->user($orgB);
        $created = $this->actingAs($editorB)->postJson('/api/webhooks', ['name' => 'Foreign', 'url' => 'https://hooks.example.com/v1', 'event_types' => ['automation.failed']])->assertCreated()->json('data');
        $revision = ResourceRevision::where(['resource_type' => 'webhook', 'resource_id' => $created['id']])->firstOrFail();
        $submission = $this->actingAs($editorB)->postJson("/api/webhooks/{$created['id']}/activation-submissions", ['revision_id' => $revision->id])->assertCreated()->json('data.id');
        ResourcePublicationSubmission::whereKey($submission)->update(['reviewer_id' => $adminA->id]);
        $this->actingAs($adminA)->getJson("/api/admin/webhook-activation-submissions/$submission")->assertNotFound();
        $this->actingAs($adminA)->getJson("/api/admin/webhook-activation-submissions/$submission/history")->assertNotFound();
        $this->actingAs($adminA)->postJson("/api/admin/webhook-activation-submissions/$submission/approve")->assertNotFound();
    }
}
