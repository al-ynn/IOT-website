<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CorePlatformTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $organization, string $role = 'owner', ?string $platformRole = null): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $role, 'platform_role' => $platformRole, 'status' => 'active']);
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'New', 'email' => 'new@example.com', 'password' => 'password123'])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertFalse(\Schema::hasTable('plans'));
        $this->assertFalse(\Schema::hasTable('subscriptions'));
    }

    public function test_user_retrieves_only_own_organization(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'b']);
        $user = $this->member($a);
        $this->actingAs($user)->getJson('/api/organization')->assertOk()->assertJsonPath('id', (string) $a->id)->assertJsonMissing(['id' => (string) $b->id]);
    }

    public function test_platform_admin_can_create_staff_and_admin_accounts(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->member($organization, 'owner', 'platform_admin');
        $staffResponse = $this->actingAs($admin)->postJson('/api/admin/users', ['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'role' => 'staff', 'organization_id' => $organization->id])->assertCreated()->assertJsonPath('role', 'staff');
        $this->actingAs($admin)->postJson('/api/admin/users', ['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'Admin123!', 'password_confirmation' => 'Admin123!', 'role' => 'admin', 'organization_id' => $organization->id])->assertCreated()->assertJsonPath('role', 'admin');
        $this->assertNull(User::find($staffResponse->json('id'))->platform_role);
        $this->assertSame('platform_admin', User::where('email', 'admin@example.test')->value('platform_role'));
    }

    public function test_staff_cannot_use_admin_routes(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $staff = $this->member($organization);
        $this->actingAs($staff)->getJson('/api/admin/users')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/admin/device-access')->assertForbidden();
    }

    public function test_device_creation_has_no_commercial_dependency_and_creator_gets_full_access(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $user = $this->member($organization);
        $response = $this->actingAs($user)->postJson('/api/devices', ['name' => 'Mine', 'type' => 'sensor', 'serialNumber' => 'mine', 'protocol' => 'mqtt'])->assertCreated()->assertJsonPath('access.level', 'full_access');
        $this->assertDatabaseHas('device_access_assignments', ['device_id' => $response->json('id'), 'user_id' => $user->id, 'access_level' => 'full_access']);
    }

    public function test_device_creation_remains_organization_scoped(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'b']);
        $user = $this->member($a);
        $foreign = Device::create(['organization_id' => $b->id, 'name' => 'Foreign', 'type' => 'sensor', 'external_id' => 'foreign', 'protocol' => 'mqtt']);
        $this->actingAs($user)->getJson("/api/devices/$foreign->id")->assertNotFound();
    }

    public function test_dashboard_creation_has_no_count_gate(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $user = $this->member($organization);
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->postJson('/api/dashboards', ['name' => "Dashboard $i", 'widgets' => []])->assertCreated();
        }$this->assertSame(3, $organization->dashboards()->count());
    }

    public function test_registration_cannot_assign_privileged_fields(): void
    {
        $payload = ['name' => 'Ordinary', 'email' => 'ordinary@example.com', 'password' => 'password123', 'platform_role' => 'platform_admin', 'role' => 'admin', 'permissions' => ['*']];
        $this->postJson('/api/auth/register', $payload)->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'ordinary@example.com']);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('invalid-token')->getJson('/api/organization')->assertUnauthorized();
    }

    public function test_auth_rate_limits_remain_security_controls(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }$this->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_staff_login_refresh_logout_and_revocation(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $staff = $this->member($organization);
        $staff->update(['email' => 'login@example.test', 'password' => Hash::make('Password123!')]);
        $login = $this->postJson('/api/auth/login', ['email' => 'login@example.test', 'password' => 'Password123!'])->assertOk();
        $token = $login->json('token');
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
