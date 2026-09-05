<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Report;
use App\Models\ResourceRevision;
use App\Models\ResourceSaveIdempotency;
use App\Models\User;
use App\Services\ResourceRevisionService;
use App\Services\ResourceSaveFingerprintService;
use App\Services\SafeResourceSaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\TestCase;

final class RevisionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_replay_returns_original_outcome_without_second_mutation_or_revision(): void
    {
        [$actor, $report, $base] = $this->context();
        $key = '11111111-1111-4111-8111-111111111111';
        $mutations = 0;
        $save = function () use ($actor, $report, $base, $key, &$mutations): Report {
            return app(SafeResourceSaveService::class)->execute(
                $actor,
                'report',
                Report::class,
                $report->id,
                $base->id,
                fn () => true,
                fn () => true,
                function (Report $locked) use (&$mutations) {
                    $mutations++;
                    $locked->update(['name' => 'Committed once']);
                    return $locked;
                },
                fn (Report $locked, User $user) => app(ResourceRevisionService::class)->recordReport($locked, $user, 'Report updated'),
                true,
                $key,
                ['name' => 'Committed once'],
            );
        };

        $first = $save();
        $revisionId = $first->getAttribute('save_idempotency_outcome')['committedRevisionId'];
        $updatedAt = $report->refresh()->updated_at;
        $second = $save();

        $this->assertSame(1, $mutations);
        $this->assertSame(2, ResourceRevision::where(['resource_type' => 'report', 'resource_id' => $report->id])->count());
        $this->assertSame($revisionId, $second->getAttribute('save_idempotency_outcome')['committedRevisionId']);
        $this->assertTrue($second->getAttribute('save_idempotency_outcome')['replayed']);
        $this->assertTrue($updatedAt->equalTo($report->refresh()->updated_at));
        $this->assertDatabaseCount('resource_save_idempotencies', 1);
    }

    public function test_same_key_with_different_command_is_rejected_without_mutation(): void
    {
        [$actor, $report, $base] = $this->context();
        $service = app(SafeResourceSaveService::class);
        $key = '22222222-2222-4222-8222-222222222222';
        $run = fn (string $name) => $service->execute(
            $actor, 'report', Report::class, $report->id, $base->id,
            fn () => true, fn () => true,
            function (Report $locked) use ($name) { $locked->update(['name' => $name]); return $locked; },
            fn (Report $locked, User $user) => app(ResourceRevisionService::class)->recordReport($locked, $user, 'Report updated'),
            true, $key, ['name' => $name],
        );

        $run('First command');
        try {
            $run('Different command');
            $this->fail('Reused key was accepted.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
            $this->assertSame('idempotency_key_reused', $exception->getResponse()->getData(true)['code']);
        }

        $this->assertSame('First command', $report->refresh()->name);
        $this->assertSame(2, ResourceRevision::where(['resource_type' => 'report', 'resource_id' => $report->id])->count());
        $this->assertDatabaseCount('resource_save_idempotencies', 1);
    }

    public function test_keys_are_hashed_actor_scoped_and_store_no_payload_or_response(): void
    {
        [$actor, $report, $base, $organization] = $this->context();
        $other = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $key = '33333333-3333-4333-8333-333333333333';
        $save = function (User $user, string $name, int $baseId) use ($report, $key): Report {
            return app(SafeResourceSaveService::class)->execute(
                $user, 'report', Report::class, $report->id, $baseId,
                fn () => true, fn () => true,
                function (Report $locked) use ($name) { $locked->update(['name' => $name]); return $locked; },
                fn (Report $locked, User $current) => app(ResourceRevisionService::class)->recordReport($locked, $current, 'Report updated'),
                true, $key, ['name' => $name],
            );
        };

        $first = $save($actor, 'Actor one', $base->id);
        $head = (int) $first->getAttribute('save_idempotency_outcome')['committedRevisionId'];
        $save($other, 'Actor two', $head);

        $this->assertDatabaseCount('resource_save_idempotencies', 2);
        $rows = ResourceSaveIdempotency::orderBy('id')->get();
        $this->assertSame(hash('sha256', $key), $rows[0]->key_hash);
        $this->assertSame($rows[0]->key_hash, $rows[1]->key_hash);
        $this->assertNotSame($rows[0]->user_id, $rows[1]->user_id);
        $serialized = $rows->toJson();
        $this->assertStringNotContainsString($key, $serialized);
        $this->assertStringNotContainsString('Actor one', $serialized);
        $this->assertStringNotContainsString('Actor two', $serialized);
    }

    public function test_fingerprint_normalizes_object_keys_but_preserves_array_order_and_base(): void
    {
        $service = app(ResourceSaveFingerprintService::class);
        $one = $service->fingerprint('dashboard', 9, 4, ['metadata' => ['b' => 2, 'a' => 1], 'widgets' => ['w1', 'w2']]);
        $same = $service->fingerprint('dashboard', '9', '4', ['widgets' => ['w1', 'w2'], 'metadata' => ['a' => 1, 'b' => 2]]);
        $reordered = $service->fingerprint('dashboard', 9, 4, ['metadata' => ['a' => 1, 'b' => 2], 'widgets' => ['w2', 'w1']]);
        $otherBase = $service->fingerprint('dashboard', 9, 5, ['metadata' => ['a' => 1, 'b' => 2], 'widgets' => ['w1', 'w2']]);

        $this->assertSame($one, $same);
        $this->assertNotSame($one, $reordered);
        $this->assertNotSame($one, $otherBase);
    }

    private function context(): array
    {
        $organization = Organization::create(['name' => 'Idempotency', 'slug' => 'idempotency-'.uniqid()]);
        $actor = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $report = $organization->reports()->create([
            'name' => 'Original',
            'report_type' => 'device_summary',
            'configuration' => ['device_ids' => []],
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $base = app(ResourceRevisionService::class)->recordReport($report, $actor, 'Baseline');
        return [$actor, $report, $base, $organization];
    }
}
