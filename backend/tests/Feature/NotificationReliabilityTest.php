<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Device;
use App\Models\NotificationOutboxEvent;
use App\Models\User;
use App\Services\NotificationMaterializationService;
use App\Services\NotificationOutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class NotificationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_materialization_preserves_user_state(): void
    {
        $organization = Organization::create(['name' => 'Reliable', 'slug' => 'reliable']);
        $recipient = User::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
        $service = app(NotificationMaterializationService::class);
        $attributes = ['title' => 'Created', 'message' => 'A device was created.', 'severity' => 'info'];

        $first = $service->createOnce($recipient, 'device.created', 'device:42:created', $attributes);
        $first->update(['read_at' => now(), 'dismissed_at' => now(), 'action_state' => 'completed']);
        $second = $service->createOnce($recipient, 'device.created', 'device:42:created', [
            ...$attributes,
            'title' => 'A retry must not replace this',
            'action_state' => 'pending',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame('Created', $second->refresh()->title);
        $this->assertNotNull($second->read_at);
        $this->assertNotNull($second->dismissed_at);
        $this->assertSame('completed', $second->action_state);
    }

    public function test_distinct_facts_and_recipients_have_distinct_rows(): void
    {
        $organization = Organization::create(['name' => 'Facts', 'slug' => 'facts']);
        $one = User::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
        $two = User::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
        $service = app(NotificationMaterializationService::class);
        $attributes = ['title' => 'Created', 'message' => 'Created.', 'severity' => 'info'];

        $service->createOnce($one, 'device.created', 'device:1:created', $attributes);
        $service->createOnce($one, 'device.created', 'device:2:created', $attributes);
        $service->createOnce($two, 'device.created', 'device:1:created', $attributes);

        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_inactive_and_muted_recipients_are_not_materialized(): void
    {
        $organization = Organization::create(['name' => 'Eligibility', 'slug' => 'eligibility']);
        $inactive = User::factory()->create(['organization_id' => $organization->id, 'status' => 'disabled']);
        $muted = User::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'notification_settings' => ['schemaVersion' => 1, 'rules' => ['device.created' => false], 'categories' => []],
        ]);
        $service = app(NotificationMaterializationService::class);
        $attributes = ['title' => 'Created', 'message' => 'Created.', 'severity' => 'info'];

        $this->assertNull($service->createOnce($inactive, 'device.created', 'device:1:created', $attributes));
        $this->assertNull($service->createOnce($muted, 'device.created', 'device:1:created', $attributes));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_delivery_cannot_set_interaction_state_or_unknown_rule(): void
    {
        $organization = Organization::create(['name' => 'Trust', 'slug' => 'trust']);
        $recipient = User::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
        $service = app(NotificationMaterializationService::class);

        try {
            $service->createOnce($recipient, 'device.created', 'device:1:created', [
                'title' => 'Created', 'message' => 'Created.', 'severity' => 'info', 'read_at' => now(),
            ]);
            $this->fail('Interaction state was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('notifications', 0);
        }

        $this->expectException(ValidationException::class);
        $service->createOnce($recipient, 'client.chosen', 'fact:1', [
            'title' => 'Unsafe', 'message' => 'Unsafe.', 'severity' => 'info',
        ]);
    }

    public function test_materialization_rejects_a_recipient_organization_mismatch(): void
    {
        $organization = Organization::create(['name' => 'Recipient Org', 'slug' => 'recipient-org']);
        $foreign = Organization::create(['name' => 'Foreign Org', 'slug' => 'foreign-org']);
        $recipient = User::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);

        try {
            app(NotificationMaterializationService::class)->createOnce(
                $recipient,
                'device.created',
                'device:1:created',
                [
                    'organization_id' => $foreign->id,
                    'title' => 'Unsafe',
                    'message' => 'Wrong tenant.',
                    'severity' => 'info',
                ],
            );
            $this->fail('Cross-Organization Notification materialization should fail closed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recipient', $exception->errors());
        }

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_device_fact_and_outbox_are_atomic_and_recoverably_materialized_once(): void
    {
        $organization=Organization::create(['name'=>'Outbox','slug'=>'outbox']);
        $creator=User::factory()->create(['organization_id'=>$organization->id,'status'=>'active','platform_role'=>null]);
        $admin=User::factory()->create(['organization_id'=>$organization->id,'status'=>'active','platform_role'=>'platform_admin']);
        $foreignOrganization=Organization::create(['name'=>'Foreign','slug'=>'outbox-foreign']);
        $foreignAdmin=User::factory()->create(['organization_id'=>$foreignOrganization->id,'status'=>'active','platform_role'=>'platform_admin']);
        $service=app(NotificationOutboxService::class);
        try{DB::transaction(function()use($organization,$creator,$service){$device=$organization->devices()->create(['name'=>'Rolled back','external_id'=>'rollback-device','status'=>'online','type'=>'sensor','protocol'=>'mqtt','created_by'=>$creator->id]);$service->recordDeviceCreated($device);throw new \RuntimeException('rollback');});}catch(\RuntimeException){}
        $this->assertDatabaseCount('devices',0);$this->assertDatabaseCount('notification_outbox_events',0);
        $device=$organization->devices()->create(['name'=>'Committed','external_id'=>'committed-device','status'=>'online','type'=>'sensor','protocol'=>'mqtt','created_by'=>$creator->id]);
        $event=$service->recordDeviceCreated($device);$service->process($event);$service->process($event->refresh());
        $this->assertDatabaseCount('notification_outbox_events',1);$this->assertDatabaseHas('notification_outbox_events',['id'=>$event->id,'processed_at'=>$event->refresh()->processed_at]);
        $this->assertSame(1,DB::table('notifications')->where(['user_id'=>$admin->id,'type'=>'device.created'])->count());
        $this->assertSame(0,DB::table('notifications')->where(['user_id'=>$foreignAdmin->id,'type'=>'device.created'])->count());
        $serialized=NotificationOutboxEvent::firstOrFail()->toJson();$this->assertStringNotContainsString('Committed',$serialized);$this->assertStringNotContainsString('secret',$serialized);
    }

    public function test_failed_generic_materialization_remains_recoverable_and_creates_once(): void
    {
        $organization=Organization::create(['name'=>'Recovery','slug'=>'notification-recovery']);
        $recipient=User::factory()->create(['organization_id'=>$organization->id,'status'=>'active']);
        $event=NotificationOutboxEvent::create([
            'event_type'=>NotificationOutboxService::MATERIALIZE,
            'fact_identity'=>hash('sha256','recovery-fact'),
            'aggregate_type'=>'device',
            'aggregate_id'=>'42',
            'payload'=>['recipient_id'=>$recipient->id,'rule_key'=>'device.created','fact_identity'=>'device:42:created','attributes'=>['organization_id'=>$organization->id,'resource_type'=>'device','resource_id'=>42,'title'=>'Recovered','message'=>'Recovered once.','severity'=>'info','requires_action'=>false]],
            'available_at'=>now(),
        ]);
        $outbox=app(NotificationOutboxService::class);
        Schema::rename('notifications','notifications_unavailable');
        try{$outbox->process($event);}catch(\Throwable $error){$outbox->markRetry($event,$error);}
        Schema::rename('notifications_unavailable','notifications');
        $this->assertDatabaseHas('notification_outbox_events',['id'=>$event->id,'processed_at'=>null,'attempts'=>1]);
        $this->assertDatabaseCount('notifications',0);

        $outbox->process($event->refresh());
        $outbox->process($event->refresh());
        $this->assertDatabaseHas('notification_outbox_events',['id'=>$event->id,'attempts'=>2]);
        $this->assertSame(1,DB::table('notifications')->where(['user_id'=>$recipient->id,'type'=>'device.created'])->count());
    }
}
