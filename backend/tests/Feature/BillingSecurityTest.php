<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_admin_billing(): void
    {
        $organization=Organization::create(['name'=>'Customer','slug'=>'customer']);
        $user=User::factory()->create(['organization_id'=>$organization->id,'role'=>'owner','platform_role'=>null]);
        $this->actingAs($user)->getJson('/api/admin/billing/stats')->assertForbidden();
    }

    public function test_usage_is_scoped_to_authenticated_organization(): void
    {
        $mine=Organization::create(['name'=>'Mine','slug'=>'mine']);
        $other=Organization::create(['name'=>'Other','slug'=>'other']);
        $user=User::factory()->create(['organization_id'=>$mine->id,'role'=>'owner']);
        Device::create(['organization_id'=>$mine->id,'name'=>'Mine']);
        Device::create(['organization_id'=>$other->id,'name'=>'Other']);
        $this->actingAs($user)->getJson('/api/billing/usage')->assertOk()->assertJson(['devices'=>1,'users'=>1,'dashboards'=>0]);
    }

    public function test_unverified_webhook_cannot_activate_billing(): void
    {
        $this->postJson('/api/billing/webhook',['type'=>'payment.succeeded','organization_id'=>1,'plan_id'=>'pro-monthly'])
            ->assertStatus(503)->assertJson(['code'=>'BILLING_PROVIDER_NOT_CONFIGURED']);
    }
}
