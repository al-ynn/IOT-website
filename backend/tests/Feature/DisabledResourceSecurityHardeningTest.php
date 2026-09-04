<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhookJob;
use App\Models\Automation;
use App\Models\Device;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Automation\AutomationExecutionService;
use App\Services\DeviceCredentialAuthenticator;
use App\Services\DeviceCredentialService;
use App\Services\ResourceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DisabledResourceSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_device_credentials_fail_authentication_but_grants_and_history_survive_restore(): void
    {
        [$organization, $admin, $staff] = $this->actors('device');
        $device = Device::create([
            'organization_id' => $organization->id, 'name' => 'Pump', 'external_id' => 'DS-80',
            'type' => 'sensor', 'protocol' => 'mqtt', 'status' => 'online', 'created_by' => $staff->id,
        ]);
        \App\Models\DeviceAccessAssignment::create([
            'device_id' => $device->id, 'user_id' => $staff->id, 'access_level' => 'full_access', 'assigned_by' => $admin->id,
        ]);
        [$credential, $token] = app(DeviceCredentialService::class)->create($admin, $device, ['name' => 'Agent']);
        $revisions = ResourceRevision::count();

        app(ResourceLifecycleService::class)->disable($admin, 'device', $device->id);
        $this->assertNull(app(DeviceCredentialAuthenticator::class)->authenticate($token));
        $this->assertDatabaseHas('device_access_assignments', ['device_id' => $device->id, 'user_id' => $staff->id]);
        $this->assertSame($revisions, ResourceRevision::count());

        app(DeviceCredentialService::class)->revoke($credential);
        app(ResourceLifecycleService::class)->restore($admin, 'device', $device->id);
        $this->assertNull(app(DeviceCredentialAuthenticator::class)->authenticate($token));
        $this->assertDatabaseHas('device_credentials', ['id' => $credential->id, 'revoked_at' => $credential->refresh()->revoked_at]);
    }

    public function test_webhook_disable_cancels_queued_delivery_and_restore_does_not_reactivate(): void
    {
        [$organization, $admin, $staff] = $this->actors('webhook');
        $webhook = Webhook::create([
            'organization_id' => $organization->id, 'name' => 'Ops Hook', 'url' => 'https://example.com/hook',
            'enabled' => true, 'event_types' => ['automation.failed'], 'signing_secret' => 'secret',
            'secret_prefix' => 'Configured', 'created_by' => $staff->id,
        ]);
        ResourceCollaborator::create([
            'resource_type' => 'webhook', 'resource_id' => $webhook->id, 'user_id' => $staff->id,
            'permission' => 'edit', 'granted_by' => $staff->id,
        ]);
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id, 'event_uuid' => (string) Str::uuid(),
            'event_type' => 'automation.failed', 'payload' => ['safe' => true], 'status' => 'pending',
        ]);

        app(ResourceLifecycleService::class)->disable($admin, 'webhook', $webhook->id);
        $this->assertFalse($webhook->refresh()->enabled);
        app(DeliverWebhookJob::class, ['deliveryId' => $delivery->id])->handle(
            app(\App\Services\WebhookUrlGuard::class),
            app(\App\Services\OperationalEventService::class),
            app(\App\Services\SystemSettingsService::class),
        );
        $this->assertSame('cancelled', $delivery->refresh()->status);
        $this->assertSame('webhook_lifecycle_inactive', $delivery->error_code);

        app(ResourceLifecycleService::class)->restore($admin, 'webhook', $webhook->id);
        $this->assertFalse($webhook->refresh()->enabled);
        $this->actingAs($admin)->getJson('/api/admin/disabled-resources?resource_type=webhook')->assertOk();
    }

    public function test_automation_disable_stops_execution_and_restore_does_not_enable_it(): void
    {
        [$organization, $admin, $staff] = $this->actors('automation');
        $automation = Automation::create([
            'organization_id' => $organization->id, 'name' => 'Rule', 'status' => 'active',
            'enabled' => true, 'version' => 1, 'created_by' => $staff->id, 'updated_by' => $staff->id,
        ]);
        ResourceCollaborator::create([
            'resource_type' => 'automation', 'resource_id' => $automation->id, 'user_id' => $staff->id,
            'permission' => 'edit', 'granted_by' => $staff->id,
        ]);
        app(\App\Services\ResourceRevisionService::class)->recordAutomation($automation, $staff, 'Created');

        app(ResourceLifecycleService::class)->disable($admin, 'automation', $automation->id);
        $this->assertFalse($automation->refresh()->enabled);
        $execution = app(AutomationExecutionService::class)->execute($automation->refresh(), 'manual', [], (string) Str::uuid());
        $this->assertSame('skipped', $execution->status);
        $this->assertStringContainsString('disabled or archived', $execution->error_message);

        app(ResourceLifecycleService::class)->restore($admin, 'automation', $automation->id);
        $this->assertFalse($automation->refresh()->enabled);
    }

    public function test_disabled_device_blocks_new_and_escalated_grants_but_allows_reduction(): void
    {
        [$organization, $admin, $staff] = $this->actors('device-access');
        $other = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $device = Device::create([
            'organization_id' => $organization->id, 'name' => 'Dormant Pump', 'external_id' => 'DS-80-ACCESS',
            'type' => 'sensor', 'protocol' => 'mqtt', 'status' => 'offline', 'created_by' => $staff->id,
        ]);
        $assignment = \App\Models\DeviceAccessAssignment::create([
            'device_id' => $device->id, 'user_id' => $staff->id, 'access_level' => 'viewer', 'assigned_by' => $admin->id,
        ]);
        app(ResourceLifecycleService::class)->disable($admin, 'device', $device->id);
        $service = app(\App\Services\Admin\DeviceAccessService::class);

        try {
            $service->create($admin, $device->id, $other->id, 'viewer');
            $this->fail('Disabled Device accepted a new assignment.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
            $this->assertDatabaseMissing('device_access_assignments', ['device_id' => $device->id, 'user_id' => $other->id]);
        }
        try {
            $service->update($assignment, 'full_access', $admin);
            $this->fail('Disabled Device accepted an access escalation.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
            $this->assertSame('viewer', $assignment->refresh()->access_level);
        }
        $service->delete($assignment, $admin);
        $this->assertDatabaseMissing('device_access_assignments', ['id' => $assignment->id]);
    }
    public function test_disabled_resource_blocks_review_claim_and_preserves_submission(): void
    {
        [$organization, $admin, $staff] = $this->actors('review');
        $templateId = $this->actingAs($staff)->postJson('/api/device-templates', ['name' => 'Template'])
            ->assertCreated()->json('data.id');
        $submissionId = $this->actingAs($staff)->postJson("/api/device-templates/$templateId/publication-submissions")
            ->assertCreated()->json('data.id');

        app(ResourceLifecycleService::class)->disable($admin, 'device_template', $templateId);
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/$submissionId/claim")
            ->assertStatus(409);
        $this->assertDatabaseHas('resource_publication_submissions', [
            'id' => $submissionId, 'resource_id' => $templateId, 'status' => 'submitted',
        ]);
    }

    private function actors(string $suffix): array
    {
        $organization = Organization::create(['name' => "Lifecycle $suffix", 'slug' => "lifecycle-$suffix"]);
        $admin = User::factory()->create([
            'organization_id' => $organization->id, 'role' => 'staff',
            'platform_role' => 'platform_admin', 'status' => 'active',
        ]);
        $staff = User::factory()->create([
            'organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active',
        ]);

        return [$organization, $admin, $staff];
    }
}
