<?php

namespace Tests\Feature;

use App\Models\DeviceTemplate;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceTemplateWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_contract_is_bounded_allowlisted_and_server_scoped(): void
    {
        [$organization, $editor] = $this->context();
        $foreign = Organization::create(['name' => 'Foreign', 'slug' => 'foreign-template-workspace']);
        $payload = ['name' => str_repeat('A', 51), 'description' => str_repeat('B', 129), 'device_type' => 'arbitrary-class', 'protocol' => 'javascript:', 'organization_id' => $foreign->id];
        $this->actingAs($editor)->postJson('/api/device-templates', $payload)->assertUnprocessable();
        $id = $this->actingAs($editor)->postJson('/api/device-templates', ['name' => 'Air Monitor', 'description' => 'Indoor conditions', 'device_type' => 'esp32', 'protocol' => 'wifi', 'organization_id' => $foreign->id])->assertCreated()->assertJsonPath('data.organization.id', (string) $organization->id)->assertJsonPath('data.dashboardConfigured', false)->json('data.id');
        $this->assertDatabaseHas('device_templates', ['id' => $id, 'organization_id' => $organization->id, 'device_type' => 'esp32', 'protocol' => 'wifi']);
    }

    public function test_template_dashboard_uses_safe_save_and_survives_reload(): void
    {
        [$organization, $editor, $template] = $this->context(true);
        $dashboard = $this->actingAs($editor)->getJson("/api/device-templates/$template->id/dashboard")->assertOk()->assertJsonPath('scope', 'template')->json();
        $widgetId = (string) Str::uuid();
        $saved = $this->actingAs($editor)->patchJson("/api/device-templates/$template->id/dashboard", ['name' => 'Template Web Dashboard', 'layoutVersion' => $dashboard['layoutVersion'], 'baseRevisionId' => $dashboard['baseRevisionId'], 'widgets' => [['id' => $widgetId, 'type' => 'label', 'settings' => ['title' => 'Installation', 'staticValue' => 'Ready'], 'layout' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 2]]]], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('widgets.0.settings.staticValue', 'Ready')->json();
        $this->actingAs($editor)->getJson("/api/device-templates/$template->id/dashboard")->assertOk()->assertJsonPath('widgets.0.id', $widgetId);
        $this->actingAs($editor)->getJson("/api/device-templates/$template->id")->assertOk()->assertJsonPath('data.dashboardConfigured', true);
        $this->assertGreaterThan($dashboard['layoutVersion'], $saved['layoutVersion']);
        $this->assertSame(2, ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $template->id])->count());
    }

    public function test_view_grant_cannot_mutate_and_template_device_creation_is_atomic_and_bound(): void
    {
        [$organization, $editor, $template] = $this->context(true);
        $viewer = User::factory()->create(['organization_id' => $organization->id, 'role' => 'staff', 'status' => 'active']);
        ResourceCollaborator::create(['resource_type' => 'device_template', 'resource_id' => $template->id, 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $editor->id]);
        $this->actingAs($viewer)->getJson("/api/device-templates/$template->id/dashboard")->assertOk()->assertJsonPath('canEdit', false);
        $this->actingAs($viewer)->postJson("/api/device-templates/$template->id/devices", ['name' => 'Blocked'])->assertNotFound();
        $device = $this->actingAs($editor)->postJson("/api/device-templates/$template->id/devices", ['name' => 'Air Node', 'template_id' => 999, 'organization_id' => 999, 'access_level' => 'admin'])->assertUnprocessable();
        $device = $this->actingAs($editor)->postJson("/api/device-templates/$template->id/devices", ['name' => 'Air Node'])->assertCreated()->json();
        $this->assertDatabaseHas('devices', ['id' => $device['id'], 'organization_id' => $organization->id, 'device_template_id' => $template->id]);
        $this->assertDatabaseHas('device_access_assignments', ['device_id' => $device['id'], 'user_id' => $editor->id, 'access_level' => 'full_access']);
    }

    private function context(bool $withTemplate = false): array
    {
        $organization = Organization::create(['name' => 'Workspace', 'slug' => 'template-workspace-'.Str::lower(Str::random(6))]);
        $editor = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        if (! $withTemplate) return [$organization, $editor];
        $template = DeviceTemplate::create(['organization_id' => $organization->id, 'created_by' => $editor->id, 'name' => 'Air Template', 'device_type' => 'esp32', 'protocol' => 'wifi']);
        app(\App\Services\ResourceRevisionService::class)->recordTemplate($template, $editor, 'Template created');
        return [$organization, $editor, $template];
    }
}
