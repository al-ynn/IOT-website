<?php

namespace Tests\Feature;

use App\Collaboration\CollaborationMetadataSanitizer;
use App\Collaboration\CollaborationRecipientResolver;
use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\FirmwareArtifact;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CollaborationFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function organization(string $suffix): Organization
    {
        return Organization::create(['name' => "Organization {$suffix}", 'slug' => "organization-{$suffix}"]);
    }

    private function user(Organization $organization, string $role = 'owner', ?string $platformRole = null): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $role, 'platform_role' => $platformRole, 'status' => 'active']);
    }

    private function device(Organization $organization, string $suffix): Device
    {
        return $organization->devices()->create(['name' => "Device {$suffix}", 'external_id' => "device-{$suffix}", 'type' => 'sensor', 'protocol' => 'https', 'status' => 'online']);
    }

    private function assign(User $user, Device $device, string $level): void
    {
        DeviceAccessAssignment::create(['user_id' => $user->id, 'device_id' => $device->id, 'access_level' => $level]);
    }

    public function test_device_resolution_delegates_to_canonical_device_access(): void
    {
        $organization = $this->organization('a');
        $staff = $this->user($organization);
        $unassigned = $this->device($organization, 'hidden');
        $viewer = $this->device($organization, 'viewer');
        $full = $this->device($organization, 'full');
        $this->assign($staff, $viewer, 'viewer');
        $this->assign($staff, $full, 'full_access');
        $registry = app(CollaborationResourceRegistry::class);

        $this->expectNotFound(fn () => $registry->resolve($staff, new CollaborationResourceReference('device', $unassigned->id)));
        $this->assertSame($viewer->id, $registry->resolve($staff, new CollaborationResourceReference('device', $viewer->id), 'view')->resource->id);
        $this->expectNotFound(fn () => $registry->resolve($staff, new CollaborationResourceReference('device', $viewer->id), 'edit'));
        $this->assertSame($full->id, $registry->resolve($staff, new CollaborationResourceReference('device', $full->id), 'edit')->resource->id);
        $this->expectNotFound(fn () => $registry->resolve($staff, new CollaborationResourceReference('device', $viewer->id), 'share'));
        $this->assertSame($full->id, $registry->resolve($staff, new CollaborationResourceReference('device', $full->id), 'share')->resource->id);
    }

    public function test_admin_authority_is_independent_and_monitored_preferences_do_not_grant_access(): void
    {
        $organizationA = $this->organization('a');
        $organizationB = $this->organization('b');
        $staff = $this->user($organizationA);
        $admin = $this->user($organizationA, 'staff', 'platform_admin');
        $foreign = $this->device($organizationB, 'foreign');
        $admin->monitoredDevices()->attach($foreign->id);
        $registry = app(CollaborationResourceRegistry::class);

        $this->expectNotFound(fn () => $registry->resolve($staff, new CollaborationResourceReference('device', $foreign->id)));
        $this->expectNotFound(fn () => $registry->resolve($admin, new CollaborationResourceReference('device', $foreign->id), 'administer'));
        $local = $this->device($organizationA, 'local');
        $this->assertSame($local->id, $registry->resolve($admin, new CollaborationResourceReference('device', $local->id), 'administer')->resource->id);

        $staff->monitoredDevices()->attach($foreign->id);
        $this->expectNotFound(fn () => $registry->resolve($staff, new CollaborationResourceReference('device', $foreign->id)));
    }

    public function test_registry_rejects_arbitrary_types_and_unknown_abilities(): void
    {
        $organization = $this->organization('a');
        $staff = $this->user($organization);
        $device = $this->device($organization, 'one');
        $registry = app(CollaborationResourceRegistry::class);

        foreach (['App\\Models\\User', 'users;drop table', '../device', 'blueprint'] as $type) {
            try {
                $registry->resolve($staff, new CollaborationResourceReference($type, $device->id));
                $this->fail("Type {$type} should have been rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('resource_type', $exception->errors());
            }
        }

        $this->assign($staff, $device, 'viewer');
        try {
            $registry->resolve($staff, new CollaborationResourceReference('device', $device->id), 'become_admin');
            $this->fail('Unknown ability should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ability', $exception->errors());
        }
    }

    public function test_registered_document_resources_enforce_tenancy_and_explicit_capabilities(): void
    {
        $organizationA = $this->organization('a');
        $organizationB = $this->organization('b');
        $staff = $this->user($organizationA);
        $templateA = $organizationA->deviceTemplates()->create(['name' => 'Template A', 'device_type' => 'sensor', 'protocol' => 'https', 'created_by' => $staff->id]);
        $templateB = $organizationB->deviceTemplates()->create(['name' => 'Template B', 'device_type' => 'sensor', 'protocol' => 'https']);
        $registry = app(CollaborationResourceRegistry::class);

        $this->assertSame($templateA->id, $registry->resolve($staff, new CollaborationResourceReference('device_template', $templateA->id), 'edit')->resource->id);
        $this->expectNotFound(fn () => $registry->resolve($staff, new CollaborationResourceReference('device_template', $templateB->id), 'view'));
        $metadata = $registry->metadata();
        $this->assertSame(['viewer', 'full_access'], $metadata['device']['permissions']);
        $this->assertSame(['view', 'edit', 'review'], $metadata['device_template']['permissions']);
        $this->assertTrue($metadata['device']['capabilities']['revisions']);
        $this->assertTrue($metadata['device_template']['capabilities']['revisions']);
        $this->assertArrayNotHasKey('modelClass', $metadata['device_template']);
    }

    public function test_registry_tenant_scope_rejects_even_a_corrupt_cross_organization_firmware_grant(): void
    {
        $organizationA = $this->organization('a');
        $organizationB = $this->organization('b');
        $staff = $this->user($organizationA, 'staff');
        $firmware = FirmwareArtifact::create([
            'organization_id' => $organizationB->id,
            'uploaded_by' => $this->user($organizationB)->id,
            'name' => 'Foreign firmware',
            'version' => '1.0.0',
            'storage_disk' => 'local',
            'storage_path' => 'private/foreign.bin',
            'original_filename' => 'foreign.bin',
            'mime_type' => 'application/octet-stream',
            'size_bytes' => 1,
            'sha256' => str_repeat('a', 64),
        ]);
        ResourceCollaborator::create([
            'resource_type' => 'firmware',
            'resource_id' => $firmware->id,
            'user_id' => $staff->id,
            'permission' => 'edit',
            'granted_by' => $staff->id,
        ]);

        $this->expectNotFound(fn () => app(CollaborationResourceRegistry::class)->resolve(
            $staff,
            new CollaborationResourceReference('firmware', $firmware->id),
            'edit',
        ));
    }

    public function test_recipient_resolution_is_active_same_organization_staff_only(): void
    {
        $organizationA = $this->organization('a');
        $organizationB = $this->organization('b');
        $actor = $this->user($organizationA);
        $eligible = $this->user($organizationA, 'staff');
        $foreign = $this->user($organizationB, 'staff');
        $disabled = $this->user($organizationA, 'staff');
        $disabled->update(['status' => 'suspended']);
        $admin = $this->user($organizationA, 'staff', 'platform_admin');
        $resolver = app(CollaborationRecipientResolver::class);

        $this->assertSame($eligible->id, $resolver->resolve($actor, $eligible->id)->id);
        foreach ([$foreign, $disabled, $admin] as $invalid) {
            try {
                $resolver->resolve($actor, $invalid->id);
                $this->fail('Ineligible recipient should have been rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('recipient_id', $exception->errors());
            }
        }
    }

    public function test_collaboration_metadata_sanitizer_redacts_exact_sensitive_keys_recursively(): void
    {
        $sanitized = app(CollaborationMetadataSanitizer::class)->sanitize([
            'authorization' => 'Bearer secret',
            'nested' => ['api-key' => 'secret', 'token_count' => 4, 'note' => 'safe'],
            'password' => 'secret',
        ]);

        $this->assertSame('[REDACTED]', $sanitized['authorization']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['api-key']);
        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame(4, $sanitized['nested']['token_count']);
        $this->assertSame('safe', $sanitized['nested']['note']);
    }

    private function expectNotFound(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected resource resolution to fail without leaking existence.');
        } catch (NotFoundHttpException) {
            $this->addToAssertionCount(1);
        }
    }
}
