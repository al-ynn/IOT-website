<?php

namespace Tests\Feature;

use App\Collaboration\CapabilityVocabulary;
use App\Collaboration\ResourceCapabilityRegistry;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CollaborationPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_viewer_and_full_access_are_derived_without_becoming_admin(): void
    {
        $org = Organization::create(['name' => 'Matrix', 'slug' => 'matrix']);
        $viewer = User::factory()->create(['organization_id' => $org->id, 'role' => 'staff', 'status' => 'active']);
        $full = User::factory()->create(['organization_id' => $org->id, 'role' => 'staff', 'status' => 'active']);
        $device = $org->devices()->create(['name'=>'D','external_id'=>'matrix-d','type'=>'sensor','protocol'=>'https','status'=>'online']);
        DeviceAccessAssignment::create(['user_id'=>$viewer->id,'device_id'=>$device->id,'access_level'=>'viewer']);
        DeviceAccessAssignment::create(['user_id'=>$full->id,'device_id'=>$device->id,'access_level'=>'full_access']);
        $matrix = app(ResourceCapabilityRegistry::class);

        $view = $matrix->for($viewer, 'device', $device);
        $manage = $matrix->for($full, 'device', $device);
        $this->assertTrue($view['canViewPrivateWorkspace']);
        $this->assertTrue($view['canComment']);
        $this->assertTrue($view['canPullUpdate']);
        $this->assertFalse($view['canEdit']);
        $this->assertFalse($view['canRequestShare']);
        $this->assertTrue($manage['canEdit']);
        $this->assertTrue($manage['canRequestShare']);
        $this->assertFalse($manage['canManageCollaborators']);
        $this->assertFalse($manage['canDirectGrant']);
    }

    public function test_unknown_capability_and_resource_type_fail_closed(): void
    {
        $this->expectException(ValidationException::class);
        app(CapabilityVocabulary::class)->assert('App\\Models\\User');
    }

    public function test_registry_declares_immediate_resources_without_pull(): void
    {
        $metadata = app(ResourceCapabilityRegistry::class)->metadata();
        $this->assertFalse($metadata['dashboard']['pullManaged']);
        $this->assertFalse($metadata['location']['pullManaged']);
        $this->assertFalse($metadata['device_template']['pullManaged']);
    }
}
