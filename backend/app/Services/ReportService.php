<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ResourceCollaborator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(private ReportConfigurationService $configuration, private SafeResourceSaveService $saves, private ReportAccessService $access, private ResourceLifecycleService $lifecycle) {}

    public function create(User $u, array $data): Report
    {
        $config = $this->configuration->validate($u, $data['report_type'], $data['configuration']);

        return DB::transaction(function () use ($u, $data, $config) {
            $r = $u->organization->reports()->create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'report_type' => $data['report_type'], 'configuration' => $config, 'created_by' => $u->id, 'updated_by' => $u->id]);
            ResourceCollaborator::updateOrCreate(['resource_type' => 'report', 'resource_id' => $r->id, 'user_id' => $u->id], ['permission' => 'edit', 'granted_by' => $u->id]);
            app(ResourceRevisionService::class)->recordReport($r, $u, 'Report created');

            return $r;
        });
    }

    public function update(User $u, Report $r, array $data, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): Report
    {
        $definitionChanged = array_key_exists('report_type', $data) || array_key_exists('configuration', $data);
        $type = $data['report_type'] ?? $r->report_type;
        $validated = $definitionChanged ? $this->configuration->validate($u, $type, $data['configuration'] ?? $r->configuration) : $r->configuration;
        $command = $data;
        if (array_key_exists('configuration', $data)) {
            $command['configuration'] = $validated;
        }

        return $this->saves->execute($u, 'report', Report::class, $r->id, $baseRevisionId, fn (User $actor, Report $locked) => abort_unless($this->access->canEdit($actor, $locked), 404), fn (Report $locked) => $this->lifecycle->assertActive('report', $locked->id, 'Disabled or Archived Reports cannot be edited.'), function (Report $locked) use ($u, $data, $type, $validated) {
            $locked->update(['name' => $data['name'] ?? $locked->name, 'description' => array_key_exists('description', $data) ? $data['description'] : $locked->description, 'report_type' => $type, 'configuration' => $validated, 'updated_by' => $u->id]);

            return $locked;
        }, fn (Report $locked, User $actor) => app(ResourceRevisionService::class)->recordReport($locked, $actor, 'Report definition updated'), true, $idempotencyKey, $command);
    }

    public function delete(Report $r): void
    {
        if ($r->runs()->whereIn('status', ['pending', 'running'])->exists()) {
            throw ValidationException::withMessages(['report' => ['An active Report Run prevents deletion.']]);
        }$r->delete();
    }

    public function cleanupRunArtifacts(Report $r): void
    {
        foreach ($r->runs()->whereNotNull('artifact_path')->get() as $run) {
            Storage::disk($run->artifact_disk)->delete($run->artifact_path);
        }
    }
}
