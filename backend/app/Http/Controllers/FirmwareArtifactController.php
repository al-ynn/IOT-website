<?php

namespace App\Http\Controllers;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Http\Resources\FirmwareArtifactResource;
use App\Models\FirmwareArtifact;
use App\Models\ResourceCollaborator;
use App\Services\FirmwareArtifactService;
use App\Services\ResourceLifecycleService;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FirmwareArtifactController extends Controller
{
    public function __construct(private FirmwareArtifactService $service, private SystemSettingsService $settings, private CollaborationResourceRegistry $registry) {}

    public function index(Request $r)
    {
        $user = $r->user();
        abort_unless($user->organization || $user->isPlatformAdmin(), 403);
        $f = $r->validate(['search' => 'nullable|string|max:100', 'device_template_id' => 'nullable|integer', 'device_type' => 'nullable|string|max:100', 'protocol' => 'nullable|string|max:50', 'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])]]);
        $q = FirmwareArtifact::query()->where('organization_id', $user->organization_id)->when(! $user->isPlatformAdmin(), fn ($query) => $query->whereIn('id', ResourceCollaborator::where('resource_type', 'firmware')->where('user_id', $user->id)->select('resource_id')))->with(['template:id,name', 'uploader:id,name', 'currentRelease'])->withCount('deployments')->when($f['search'] ?? null, fn ($x, $v) => $x->where(fn ($n) => $n->where('name', 'like', "%{$v}%")->orWhere('version', 'like', "%{$v}%")))->when($f['device_template_id'] ?? null, fn ($x, $v) => $x->where('device_template_id', $v))->when($f['device_type'] ?? null, fn ($x, $v) => $x->where('device_type', $v))->when($f['protocol'] ?? null, fn ($x, $v) => $x->where('protocol', $v))->latest();

        return FirmwareArtifactResource::collection($q->paginate($f['per_page'] ?? 25)->withQueryString());
    }

    public function store(Request $r)
    {
        $org = $this->org($r, 'device.manage');
        $max = $this->settings->integer('firmware_max_upload_mb') * 1024;
        $data = $r->validate(['name' => 'required|string|max:150', 'version' => ['required', 'string', 'max:80', Rule::unique('firmware_artifacts')->where(fn ($q) => $q->where('organization_id', $org->id)->where('name', $r->input('name')))], 'description' => 'nullable|string|max:1000', 'device_template_id' => 'nullable|integer', 'device_type' => 'nullable|string|max:100', 'protocol' => 'nullable|string|max:50', 'firmware' => ['required', 'file', 'max:'.$max]]);

        return (new FirmwareArtifactResource($this->service->detail($this->service->create($org, $r->user(), $data, $r->file('firmware')))))->response()->setStatusCode(201);
    }

    public function show(Request $r, string $artifact)
    {
        return new FirmwareArtifactResource($this->service->detail($this->resolve($r, $artifact, 'view')));
    }

    public function update(Request $r, string $artifact)
    {
        $data = $r->validate(['name' => 'sometimes|required|string|max:150', 'description' => 'sometimes|nullable|string|max:1000', 'baseRevisionId' => 'sometimes|integer']);
        $resource = $this->resolve($r, $artifact, 'edit');
        app(ResourceLifecycleService::class)->assertActive('firmware', $resource->id, 'Disabled or Archived Firmware cannot be edited.');
        $base = $data['baseRevisionId'] ?? null;
        unset($data['baseRevisionId']);

        return new FirmwareArtifactResource($this->service->update($resource, $data, $r->user(), $base, $r->header('Idempotency-Key')));
    }

    public function download(Request $r, string $artifact)
    {
        $a = $this->resolve($r, $artifact, 'view');
        abort_unless(Storage::disk($a->storage_disk)->exists($a->storage_path), 404);

        return Storage::disk($a->storage_disk)->download($a->storage_path, $a->original_filename, ['Content-Type' => $a->mime_type, 'X-Content-SHA256' => $a->sha256, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $r, string $artifact)
    {
        $resource = $this->resolve($r, $artifact, 'edit');
        app(ResourceLifecycleService::class)->assertActive('firmware', $resource->id);
        $this->service->delete($resource);

        return response()->noContent();
    }

    private function org(Request $r, string $permission)
    {
        abort_unless($r->user()->organization && $r->user()->hasOrganizationPermission($permission), 403);

        return $r->user()->organization;
    }

    private function find(Request $r, string $id): FirmwareArtifact
    {
        return $r->user()->organization->firmwareArtifacts()->findOrFail($id);
    }

    private function resolve(Request $request, string $id, string $ability): FirmwareArtifact
    {
        return $this->registry->resolve($request->user(), new CollaborationResourceReference('firmware',(int) $id), $ability)->resource;
    }
}
