<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Organization;
use App\Models\User;
use App\Services\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class B01FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'staff',
            'platform_role' => $admin ? 'platform_admin' : null,
            'status' => 'active',
        ]);
    }

    public function test_login_and_current_user_match_the_frontend_contract_without_secrets(): void
    {
        $organization = Organization::create(['name' => 'Contract', 'slug' => 'contract']);
        $user = $this->user($organization);
        $user->update(['email' => 'staff@example.test', 'password' => Hash::make('Password123!')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'staff@example.test',
            'password' => 'Password123!',
        ])->assertOk()->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'organizationId', 'role', 'platformRole', 'createdAt'],
        ])->assertJsonPath('user.organizationId', (string) $organization->id);

        foreach (['password', 'remember_token', 'token_hash', 'organization_id', 'platform_role'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $response->json('user'));
        }

        $this->withToken($response->json('token'))->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('organizationId', (string) $organization->id);
    }

    public function test_login_rejects_scope_and_authority_fields(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.test',
            'password' => 'Password123!',
            'organization_id' => 999,
            'role' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id', 'role']);
    }

    public function test_every_non_active_account_and_organization_is_denied_immediately(): void
    {
        $organization = Organization::create(['name' => 'Active', 'slug' => 'active']);
        $user = $this->user($organization);

        $this->actingAs($user)->getJson('/api/auth/me')->assertOk();
        $user->update(['status' => 'inactive']);
        $this->actingAs($user->fresh())->getJson('/api/auth/me')->assertForbidden();

        $user->update(['status' => 'active']);
        $organization->update(['status' => 'suspended']);
        $this->actingAs($user->fresh())->getJson('/api/auth/me')->assertForbidden();
    }

    public function test_current_organization_is_server_derived_and_never_global(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'foundation-a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'foundation-b']);
        $admin = $this->user($a, true);

        $this->assertTrue($admin->isAdmin());
        $this->assertSame('admin', $admin->productRole());
        $this->assertSame($a->id, app(CurrentOrganization::class)->require($admin)->id);

        $this->actingAs($admin)->getJson('/api/admin/users?organization_id='.$b->id.'&global=true')
            ->assertNotFound();

        $admin->update(['organization_id' => null]);
        $this->actingAs($admin->fresh())->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_legacy_admin_role_is_current_organization_only_and_device_access_does_not_promote_staff(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'role-a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'role-b']);
        $admin = $this->user($a, true);
        $staff = $this->user($a);
        $foreignDevice = Device::create([
            'organization_id' => $b->id,
            'name' => 'Foreign',
            'type' => 'sensor',
            'external_id' => 'B01-FOREIGN',
            'protocol' => 'mqtt',
        ]);

        $this->assertFalse($staff->isAdmin());
        $this->assertSame('staff', $staff->productRole());
        $this->actingAs($admin)->getJson('/api/admin/devices/'.$foreignDevice->id)->assertNotFound();
    }
}
