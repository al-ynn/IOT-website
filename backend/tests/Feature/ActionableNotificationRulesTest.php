<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\ResourceShareRequest;
use App\Models\User;
use App\Notifications\NotificationActionRegistry;
use App\Notifications\NotificationRuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ActionableNotificationRulesTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $o): User
    {
        return User::factory()->create(['organization_id' => $o->id, 'role' => 'staff', 'status' => 'active']);
    }

    public function test_registry_is_allowlisted_and_declares_policy(): void
    {
        $r = app(NotificationRuleRegistry::class);
        $this->assertSame('server_derived', $r->definition('access.requested')['recipientPolicy']);
        $this->assertSame('latest_per_resource', $r->definition('revision.available')['coalescing']);
        $this->expectException(ValidationException::class);
        $r->definition('App\\Notifications\\Evil');
    }

    public function test_share_actionability_is_live_and_read_does_not_accept(): void
    {
        $o = Organization::create(['name' => 'Rules', 'slug' => 'rules']);
        $sender = $this->user($o);
        $recipient = $this->user($o);
        $share = ResourceShareRequest::create(['resource_type' => 'location', 'resource_id' => 99, 'sender_user_id' => $sender->id, 'recipient_user_id' => $recipient->id, 'requested_permission' => 'view', 'status' => 'pending_recipient', 'active_key' => 'location:99:'.$recipient->id]);
        $n = Notification::create(['organization_id' => $o->id, 'user_id' => $recipient->id, 'type' => 'access.requested', 'schema_version' => 1, 'category' => 'requests', 'actor_id' => $sender->id, 'resource_type' => 'location', 'resource_id' => 99, 'data' => ['share_request_id' => $share->id], 'requires_action' => true, 'title' => 'Request', 'message' => 'Request', 'severity' => 'info']);
        $this->actingAs($recipient)->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.requiresAction', true)->assertJsonPath('data.0.actionState', 'pending_recipient')->assertJsonPath('data.0.actions.0.kind', 'navigate');
        $this->actingAs($recipient)->postJson("/api/notifications/{$n->id}/read")->assertOk();
        $this->assertSame('pending_recipient', $share->refresh()->status);
        $share->update(['status' => 'cancelled', 'active_key' => null, 'cancelled_at' => now()]);
        $this->actingAs($recipient)->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.requiresAction', false)->assertJsonPath('data.0.actionState', 'cancelled')->assertJsonCount(0, 'data.0.actions');
        $this->actingAs($recipient)->getJson('/api/notifications?filter=action_required')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unknown_event_and_external_url_are_informational_only(): void
    {
        $o = Organization::create(['name' => 'Rules', 'slug' => 'rules2']);
        $u = $this->user($o);
        Notification::create(['organization_id' => $o->id, 'user_id' => $u->id, 'type' => 'evil.class', 'category' => 'updates', 'action_url' => 'https://evil.invalid', 'title' => 'Legacy', 'message' => 'Legacy', 'severity' => 'info']);
        $this->actingAs($u)->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.eventType', 'unknown')->assertJsonPath('data.0.requiresAction', false)->assertJsonPath('data.0.deepLink', null)->assertJsonCount(0, 'data.0.actions');
    }

    public function test_action_registry_is_typed_and_unknown_actions_are_rejected(): void
    {
        $actions = app(NotificationActionRegistry::class);
        $this->assertSame('navigate', $actions->definition('open_comment')['kind']);
        $this->assertSame('Open comment', $actions->navigation('open_comment', '/app/devices/1')['label']);
        try {
            $actions->definition('POST https://evil.invalid');
            $this->fail('Unknown action accepted');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_registry_category_and_phase70_actor_summary_override_legacy_row_fields(): void
    {
        $o = Organization::create(['name' => 'Rules', 'slug' => 'rules3']);
        $actor = $this->user($o);
        $recipient = $this->user($o);
        $share = ResourceShareRequest::create(['resource_type' => 'location', 'resource_id' => 100, 'sender_user_id' => $actor->id, 'recipient_user_id' => $recipient->id, 'requested_permission' => 'view', 'status' => 'pending_recipient', 'active_key' => 'location:100:'.$recipient->id]);
        Notification::create(['organization_id' => $o->id, 'user_id' => $recipient->id, 'type' => 'access.requested', 'category' => 'access', 'actor_id' => $actor->id, 'resource_type' => 'location', 'resource_id' => 100, 'data' => ['share_request_id' => $share->id], 'requires_action' => false, 'title' => 'Request', 'message' => 'Request', 'severity' => 'info']);
        $response = $this->actingAs($recipient)->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.category', 'requests')->assertJsonStructure(['data' => [['actor' => ['id', 'displayName', 'state']]]])->assertJsonPath('data.0.requiresAction', true);
        $this->assertArrayNotHasKey('email', $response->json('data.0.actor'));
    }

    public function test_foreign_organization_admin_actions_are_not_actionable(): void
    {
        $organizationA = Organization::create(['name' => 'Org A', 'slug' => 'notification-action-org-a']);
        $organizationB = Organization::create(['name' => 'Org B', 'slug' => 'notification-action-org-b']);
        $adminA = User::factory()->create(['organization_id' => $organizationA->id, 'role' => 'staff', 'platform_role' => 'platform_admin', 'status' => 'active']);
        $senderB = $this->user($organizationB);
        $recipientB = $this->user($organizationB);
        $senderB->update(['role' => 'owner']);
        $share = ResourceShareRequest::create([
            'resource_type' => 'location', 'resource_id' => 55,
            'sender_user_id' => $senderB->id, 'recipient_user_id' => $recipientB->id,
            'requested_permission' => 'view', 'status' => 'awaiting_admin_approval',
            'active_key' => 'location:55:'.$recipientB->id,
        ]);
        Notification::create([
            'organization_id' => $organizationA->id, 'user_id' => $adminA->id,
            'type' => 'access.awaiting_approval', 'category' => 'approvals',
            'resource_type' => 'location', 'resource_id' => 55,
            'data' => ['share_request_id' => $share->id], 'requires_action' => true,
            'title' => 'Foreign request', 'message' => 'Must not be actionable.', 'severity' => 'info',
        ]);

        $this->actingAs($adminA)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.requiresAction', false)
            ->assertJsonPath('data.0.deepLink', null)
            ->assertJsonCount(0, 'data.0.actions');

        $templateId = $this->actingAs($senderB)->postJson('/api/device-templates', ['name' => 'Foreign Template'])
            ->assertCreated()->json('data.id');
        $submissionId = $this->actingAs($senderB)->postJson("/api/device-templates/{$templateId}/publication-submissions")
            ->assertCreated()->json('data.id');
        Notification::create([
            'organization_id' => $organizationA->id, 'user_id' => $adminA->id,
            'type' => 'template.publication.submitted', 'category' => 'approvals',
            'resource_type' => 'device_template', 'resource_id' => $templateId,
            'data' => ['submission_id' => $submissionId], 'requires_action' => true,
            'title' => 'Foreign review', 'message' => 'Must not be actionable.', 'severity' => 'info',
        ]);

        $this->actingAs($adminA)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.requiresAction', false)
            ->assertJsonPath('data.0.deepLink', null)
            ->assertJsonCount(0, 'data.0.actions');
    }
}
