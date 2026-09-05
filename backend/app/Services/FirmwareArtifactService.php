<?php

namespace App\Services;

use App\Collaboration\FirmwareCollaborationAuthorizer;
use App\Models\FirmwareArtifact;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FirmwareArtifactService
{
    public function __construct(private SafeResourceSaveService $saves, private FirmwareCollaborationAuthorizer $authorizer, private ResourceLifecycleService $lifecycle, private ResourceRevisionService $revisions) {}

    public function create(Organization $org, User $user, array $data, UploadedFile $file): FirmwareArtifact
    {
        $template = isset($data['device_template_id']) ? $org->deviceTemplates()->findOrFail($data['device_template_id']) : null;
        $disk = (string) config('firmware.disk', 'local');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, config('firmware.extensions'), true)) {
            throw ValidationException::withMessages(['firmware' => ['Unsupported firmware file extension.']]);
        }$path = 'firmware/'.$org->id.'/'.Str::uuid().'.'.$extension;
        $stored = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        if (! $stored) {
            throw ValidationException::withMessages(['firmware' => ['Firmware storage failed.']]);
        }try {
            $artifact = $org->firmwareArtifacts()->create(['uploaded_by' => $user->id, 'device_template_id' => $template?->id, 'name' => $data['name'], 'version' => $data['version'], 'description' => $data['description'] ?? null, 'device_type' => $data['device_type'] ?? null, 'protocol' => $data['protocol'] ?? null, 'storage_disk' => $disk, 'storage_path' => $path, 'original_filename' => basename(str_replace('\\', '/', $file->getClientOriginalName())), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())]);
            ResourceCollaborator::create(['resource_type' => 'firmware', 'resource_id' => $artifact->id, 'user_id' => $user->id, 'permission' => 'edit', 'granted_by' => $user->id]);
            app(ResourceRevisionService::class)->recordFirmware($artifact, $user, 'Firmware artifact uploaded');

            return $artifact;
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    public function update(FirmwareArtifact $artifact, array $data, User $actor, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): FirmwareArtifact
    {
        $saved = $this->saves->execute($actor, 'firmware', FirmwareArtifact::class, $artifact->id, $baseRevisionId, fn (User $user, FirmwareArtifact $locked) => abort_unless($this->authorizer->canEdit($user, $locked), 404), fn (FirmwareArtifact $locked) => $this->lifecycle->assertActive('firmware', $locked->id, 'Disabled or Archived Firmware cannot be edited.'), function (FirmwareArtifact $locked) use ($data) {
            $locked->update(array_intersect_key($data, array_flip(['name', 'description'])));

            return $locked;
        }, fn (FirmwareArtifact $locked, User $user) => $this->revisions->recordFirmware($locked, $user, 'Firmware metadata updated'), $baseRevisionId !== null, $idempotencyKey, $data);

        return $this->detail($saved);
    }

    public function detail(FirmwareArtifact $artifact): FirmwareArtifact
    {
        return $artifact->load(['template:id,name', 'uploader:id,name', 'currentRelease'])->loadCount('deployments');
    }

    public function delete(FirmwareArtifact $artifact): void
    {
        if ($artifact->deployments()->exists() || $artifact->releases()->exists() || $artifact->revisions()->count() > 1) {
            throw ValidationException::withMessages(['artifact' => ['Artifacts with revision, release, or deployment history cannot be deleted.']]);
        }$disk = $artifact->storage_disk;
        $path = $artifact->storage_path;
        $artifact->delete();
        Storage::disk($disk)->delete($path);
    }
}
