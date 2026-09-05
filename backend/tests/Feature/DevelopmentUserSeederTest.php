<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\DeviceAccessAssignment;
use App\Models\Dashboard;
use App\Models\Device;
use App\Models\DeviceTemplate;
use App\Models\OperationalEvent;
use App\Models\TelemetryRecord;
use App\Models\User;
use App\Services\Dashboard\DashboardWidgetRegistry;
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

        $staff = User::where('email', 'staff@iot-platform.test')->sole();
        $admin = User::where('email', 'admin@iot-platform.test')->sole();
        $organization = Organization::where('slug', 'sample-iot-demo')->sole();

        $this->assertSame(1, User::where('email', $staff->email)->count());
        $this->assertSame(1, User::where('email', $admin->email)->count());
        $this->assertSame(1, Organization::where('slug', $organization->slug)->count());
        $this->assertSame($organization->id, $staff->organization_id);
        $this->assertNull($staff->platform_role);
        $this->assertSame($organization->id, $admin->organization_id);
        $this->assertTrue($admin->isPlatformAdmin());
        $this->assertSame('active', $staff->status);
        $this->assertSame('active', $admin->status);
        $this->assertSame(1, DeviceAccessAssignment::where('user_id', $staff->id)->count());
        $template = DeviceTemplate::where('organization_id', $organization->id)->sole();
        $device = Device::where('organization_id', $organization->id)->sole();
        $dashboard = Dashboard::where('organization_id', $organization->id)->sole();
        $this->assertSame('Sample Environmental Controller', $template->name);
        $this->assertSame($template->id, $device->device_template_id);
        $this->assertSame('Sample Device Dashboard', $dashboard->name);
        $this->assertSame(9, $template->parameters()->count());
        $this->assertSame(873, TelemetryRecord::where('device_id', $device->id)->count());
        $this->assertSame(24, OperationalEvent::where('device_id', $device->id)->count());
        $this->assertSame(count(app(DashboardWidgetRegistry::class)->definitions('personal')), $dashboard->widgets()->count());
        $this->actingAs($admin)->getJson('/api/dashboards/default')
            ->assertOk()
            ->assertJsonPath('name', 'Sample Device Dashboard')
            ->assertJsonCount(count(app(DashboardWidgetRegistry::class)->definitions('personal')), 'widgets');
    }

    public function test_seeded_accounts_authenticate_through_the_real_login_endpoint(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/auth/login', ['email' => 'staff@iot-platform.test', 'password' => 'Password123!'])
            ->assertOk()->assertJsonPath('user.email', 'staff@iot-platform.test')->assertJsonPath('user.platformRole', null)->assertJsonStructure(['token']);
        $this->postJson('/api/auth/login', ['email' => 'admin@iot-platform.test', 'password' => 'Admin123!'])
            ->assertOk()->assertJsonPath('user.email', 'admin@iot-platform.test')->assertJsonPath('user.platformRole', 'platform_admin')->assertJsonStructure(['token']);
    }

    public function test_seeded_staff_and_admin_keep_existing_authorization_and_operational_rules(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::where('email', 'staff@iot-platform.test')->sole();
        $admin = User::where('email', 'admin@iot-platform.test')->sole();

        $this->actingAs($staff)->getJson('/api/dashboards')->assertOk();
        $this->actingAs($staff)->getJson('/api/devices')->assertOk();
        $this->actingAs($staff)->getJson('/api/analytics/summary')->assertOk();
        $this->actingAs($staff)->getJson('/api/automations')->assertOk();
        $this->actingAs($staff)->getJson('/api/admin/users')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/users')->assertOk();
    }

    public function test_development_accounts_are_not_seeded_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');
        (new \Database\Seeders\DevelopmentUserSeeder())->run();

        $this->assertDatabaseMissing('users', ['email' => 'staff@iot-platform.test']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@iot-platform.test']);
        $this->assertDatabaseMissing('organizations', ['slug' => 'sample-iot-demo']);
    }
}
