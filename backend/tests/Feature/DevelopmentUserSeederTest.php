<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_accounts_are_idempotent_and_use_canonical_models(): void
    {
        $this->seed(DatabaseSeeder::class);
        (new \Database\Seeders\DevelopmentUserSeeder())->run();

        $customer = User::where('email', 'customer@iot-platform.test')->sole();
        $admin = User::where('email', 'admin@iot-platform.test')->sole();
        $organization = Organization::where('slug', 'iot-platform-demo')->sole();

        $this->assertSame(1, User::where('email', $customer->email)->count());
        $this->assertSame(1, User::where('email', $admin->email)->count());
        $this->assertSame(1, Organization::where('slug', $organization->slug)->count());
        $this->assertSame($organization->id, $customer->organization_id);
        $this->assertNull($customer->platform_role);
        $this->assertNull($admin->organization_id);
        $this->assertTrue($admin->isPlatformAdmin());
        $this->assertSame('active', $customer->status);
        $this->assertSame('active', $admin->status);
        $this->assertSame(1, Subscription::where('organization_id', $organization->id)->count());
        $this->assertSame('free', $organization->subscription()->firstOrFail()->plan_id);
        $this->assertSame('active', $organization->subscription()->firstOrFail()->status);
    }

    public function test_seeded_accounts_authenticate_through_the_real_login_endpoint(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/auth/login', ['email' => 'customer@iot-platform.test', 'password' => 'Password123!'])
            ->assertOk()->assertJsonPath('user.email', 'customer@iot-platform.test')->assertJsonPath('user.platformRole', null)->assertJsonStructure(['token']);
        $this->postJson('/api/auth/login', ['email' => 'admin@iot-platform.test', 'password' => 'Admin123!'])
            ->assertOk()->assertJsonPath('user.email', 'admin@iot-platform.test')->assertJsonPath('user.platformRole', 'platform_admin')->assertJsonStructure(['token']);
    }

    public function test_seeded_customer_and_admin_keep_existing_authorization_and_billing_rules(): void
    {
        $this->seed(DatabaseSeeder::class);
        $customer = User::where('email', 'customer@iot-platform.test')->sole();
        $admin = User::where('email', 'admin@iot-platform.test')->sole();

        $this->actingAs($customer)->getJson('/api/dashboards')->assertOk();
        $this->actingAs($customer)->getJson('/api/devices')->assertOk();
        $this->actingAs($customer)->getJson('/api/billing/subscription')->assertOk()->assertJsonPath('planId', 'free');
        $this->actingAs($customer)->getJson('/api/analytics/summary')->assertForbidden()->assertJson(['code' => 'FEATURE_NOT_AVAILABLE']);
        $this->actingAs($customer)->getJson('/api/automations')->assertForbidden()->assertJson(['code' => 'FEATURE_NOT_AVAILABLE']);
        $this->actingAs($customer)->getJson('/api/admin/billing/stats')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/billing/stats')->assertOk();
    }

    public function test_development_accounts_are_not_seeded_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');
        (new \Database\Seeders\DevelopmentUserSeeder())->run();

        $this->assertDatabaseMissing('users', ['email' => 'customer@iot-platform.test']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@iot-platform.test']);
        $this->assertDatabaseMissing('organizations', ['slug' => 'iot-platform-demo']);
    }
}
