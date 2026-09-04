<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Models\Organization;
use App\Models\ReportRun;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationVersion;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ReportPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function actors(): array
    {
        $org = Organization::create(['name' => 'Reports Org', 'slug' => 'report-publication']);
        $editor = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner', 'status' => 'active']);
        $published = User::factory()->create(['organization_id' => $org->id, 'role' => 'viewer', 'status' => 'active']);
        $admin = User::factory()->create(['organization_id' => $org->id, 'platform_role' => 'platform_admin', 'role' => 'owner', 'status' => 'active']);
        $device = $org->devices()->create(['name' => 'Protected Pump', 'external_id' => 'published-pump', 'type' => 'pump', 'protocol' => 'mqtt']);
        $editor->deviceAccessAssignments()->create(['device_id' => $device->id, 'access_level' => 'viewer']);

        return [$org, $editor, $published, $admin, $device];
    }

    private function report(User $editor, $device): int
    {
        return (int) $this->actingAs($editor)->postJson('/api/reports', ['name' => 'Published name', 'description' => 'Approved definition', 'report_type' => 'device_summary', 'configuration' => ['device_ids' => [$device->id]]])->assertCreated()->json('data.id');
    }

    private function approve(User $editor, User $admin, int $id): ResourcePublicationState
    {
        $revision = ResourceRevision::where(['resource_type' => 'report', 'resource_id' => $id])->firstOrFail();
        $submission = $this->actingAs($editor)->postJson("/api/reports/{$id}/publication-submissions", ['revision_id' => $revision->id])->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/report-publication-submissions/{$submission}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/report-publication-submissions/{$submission}/approve")->assertOk();

        return ResourcePublicationState::where(['resource_type' => 'report', 'resource_id' => $id])->firstOrFail();
    }

    public function test_exact_revision_is_approved_without_run_or_report_copy(): void
    {
        [$org,$editor,,$admin,$device] = $this->actors();
        $id = $this->report($editor, $device);
        $revision = ResourceRevision::where(['resource_type' => 'report', 'resource_id' => $id])->firstOrFail();
        $state = $this->approve($editor, $admin, $id);
        $this->assertSame($revision->id, $state->approved_revision_id);
        $this->assertSame(1, ResourcePublicationVersion::where('resource_type', 'report')->count());
        $this->assertSame(1, $org->reports()->count());
        $this->assertSame(0, ReportRun::count());
    }

    public function test_published_only_view_uses_approved_snapshot_and_redacts_sources(): void
    {
        [, $editor,$published,$admin,$device] = $this->actors();
        $id = $this->report($editor, $device);
        $this->approve($editor, $admin, $id);
        $this->actingAs($editor)->patchJson("/api/reports/{$id}", ['name' => 'Private newer name', 'baseRevisionId' => ResourceRevision::where(['resource_type' => 'report', 'resource_id' => $id])->latest('revision_number')->value('id')])->assertOk();
        $this->actingAs($published)->getJson("/api/reports/{$id}")->assertNotFound();
        $this->actingAs($published)->getJson("/api/reports/published/{$id}")->assertOk()->assertJsonPath('data.name', 'Published name')->assertJsonPath('data.capabilities.canEdit', false)->assertJsonPath('data.capabilities.canRun', false)->assertJsonPath('data.configuration.restrictedSources', true);
    }

    public function test_published_run_pins_version_and_revision_after_independent_source_authorization(): void
    {
        [, $editor,$published,$admin,$device] = $this->actors();
        Queue::fake();
        $id = $this->report($editor, $device);
        $state = $this->approve($editor, $admin, $id);
        $published->deviceAccessAssignments()->create(['device_id' => $device->id, 'access_level' => 'viewer']);
        $this->actingAs($published)->postJson("/api/reports/published/{$id}/runs")->assertAccepted();
        $run = ReportRun::firstOrFail();
        $this->assertSame($published->id, $run->requested_by);
        $this->assertSame($state->approved_revision_id, $run->report_revision_id);
        $this->assertSame($state->current_publication_version_id, $run->publication_version_id);
        Queue::assertPushed(GenerateReportJob::class);
    }

    public function test_staff_cannot_decide_and_duplicate_approval_creates_one_version(): void
    {
        [, $editor,,$admin,$device] = $this->actors();
        $id = $this->report($editor, $device);
        $s = $this->actingAs($editor)->postJson("/api/reports/{$id}/publication-submissions")->assertCreated()->json('data.id');
        $this->actingAs($editor)->postJson("/api/admin/report-publication-submissions/{$s}/approve")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/report-publication-submissions/{$s}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/report-publication-submissions/{$s}/approve")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/report-publication-submissions/{$s}/approve")->assertUnprocessable();
        $this->assertSame(1,ResourcePublicationVersion::where('resource_type','report')->count());
    }
}
