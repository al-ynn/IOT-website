<?php
namespace Tests\Feature;
use App\Services\AdminReviewCenterService;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Facades\Route;use Illuminate\Support\Facades\Schema;use Tests\TestCase;
final class IntegrationDomainBoundaryTest extends TestCase{use RefreshDatabase;
 public function test_no_fake_integration_aggregate_or_schema_exists():void{$this->assertFalse(class_exists(\App\Models\Integration::class));$this->assertFalse(class_exists(\App\Services\IntegrationService::class));$this->assertFalse(Schema::hasTable('integrations'));$this->assertFalse(Schema::hasTable('integration_credentials'));$this->assertFalse(Schema::hasTable('oauth_connections'));}
 public function test_no_integration_api_publication_or_review_routes_exist():void{$uris=collect(Route::getRoutes())->map(fn($r)=>$r->uri());$this->assertFalse($uris->contains(fn($uri)=>str_contains($uri,'integrations/')));$this->assertFalse($uris->contains(fn($uri)=>str_contains($uri,'integration-publication')));$this->assertFalse($uris->contains(fn($uri)=>str_contains($uri,'integration-review')));}
 public function test_integration_is_not_registered_and_webhook_activation_is_distinct():void{$this->assertNotContains('integration',AdminReviewCenterService::TYPES);$this->assertContains('webhook',AdminReviewCenterService::TYPES);}
 public function test_webhook_remains_distinct_and_secret_is_hidden():void{$this->assertTrue(class_exists(\App\Models\Webhook::class));$webhook=new \App\Models\Webhook(['signing_secret'=>'sensitive']);$this->assertArrayNotHasKey('signing_secret',$webhook->toArray());$this->assertContains('signing_secret',$webhook->getHidden());}
}
