<?php

namespace Tests\Feature;

use App\Collaboration\AutomationCollaborationAuthorizer;
use App\Collaboration\DeviceCollaborationAuthorizer;
use App\Collaboration\DeviceTemplateCollaborationAuthorizer;
use App\Collaboration\FirmwareCollaborationAuthorizer;
use App\Collaboration\LocationResourceAuthorizer;
use App\Collaboration\ReportCollaborationAuthorizer;
use App\Collaboration\WebhookCollaborationAuthorizer;
use App\Models\Automation;
use App\Models\Device;
use App\Models\DeviceTemplate;
use App\Models\FirmwareArtifact;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminTenantAuthorizerBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_authorizers_allow_current_organization_and_deny_foreign_resources(): void
    {
        $current = Organization::create(['name' => 'Current', 'slug' => 'authorizer-current']);
        $foreign = Organization::create(['name' => 'Foreign', 'slug' => 'authorizer-foreign']);
        $admin = User::factory()->create(['organization_id' => $current->id, 'platform_role' => 'platform_admin', 'status' => 'active']);
        $cases = [
            [AutomationCollaborationAuthorizer::class, Automation::class],
            [DeviceCollaborationAuthorizer::class, Device::class],
            [DeviceTemplateCollaborationAuthorizer::class, DeviceTemplate::class],
            [FirmwareCollaborationAuthorizer::class, FirmwareArtifact::class],
            [LocationResourceAuthorizer::class, Location::class],
            [ReportCollaborationAuthorizer::class, Report::class],
            [WebhookCollaborationAuthorizer::class, Webhook::class],
        ];

        foreach ($cases as [$authorizerClass, $modelClass]) {
            /** @var Model $local */
            $local = new $modelClass(['organization_id' => $current->id]);
            $local->setAttribute('id', 1001);
            /** @var Model $outside */
            $outside = new $modelClass(['organization_id' => $foreign->id]);
            $outside->setAttribute('id', 2002);
            $authorizer = app($authorizerClass);

            $this->assertTrue($authorizer->canView($admin, $local), "$authorizerClass must allow the current organization Admin.");
            $this->assertFalse($authorizer->canView($admin, $outside), "$authorizerClass leaked a foreign resource.");
            $this->assertFalse($authorizer->canAdminister($admin, $outside), "$authorizerClass granted foreign administration.");
        }
    }
}
