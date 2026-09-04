<?php
namespace Tests\Feature;
use App\Models\Organization;use App\Models\User;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Facades\Schema;use Tests\TestCase;
class CommercialRemovalTest extends TestCase{
 use RefreshDatabase;
 public function test_commercial_tables_are_absent():void{foreach(['plans','subscriptions','payment_events'] as $table)$this->assertFalse(Schema::hasTable($table));}
 public function test_commercial_api_routes_do_not_exist():void{$organization=Organization::create(['name'=>'Org','slug'=>'org']);$user=User::factory()->create(['organization_id'=>$organization->id,'role'=>'owner']);foreach(['/api/billing/webhook','/api/billing/plans','/api/billing/subscription','/api/plans','/api/subscriptions','/api/checkout','/api/payments'] as $route)$this->actingAs($user)->getJson($route)->assertNotFound();}
 public function test_analytics_does_not_require_commercial_state():void{$organization=Organization::create(['name'=>'Org','slug'=>'org']);$user=User::factory()->create(['organization_id'=>$organization->id,'role'=>'owner']);$this->actingAs($user)->getJson('/api/analytics/summary')->assertOk()->assertJsonPath('totalDevices',0);}
}
