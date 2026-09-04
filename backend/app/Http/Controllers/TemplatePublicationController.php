<?php

namespace App\Http\Controllers;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\DeviceTemplate;
use App\Services\TemplatePublicationService;
use App\Services\ReviewHistoryService;
use Illuminate\Http\Request;

final class TemplatePublicationController extends Controller
{
    public function __construct(private CollaborationResourceRegistry $registry, private TemplatePublicationService $publication, private ReviewHistoryService $history) {}

    public function state(Request $request, string $template)
    {
        $resource = $this->registry->resolve($request->user(), new CollaborationResourceReference('device_template', $template), 'view')->resource;
        return response()->json(['data' => $this->publication->state($resource)]);
    }

    public function submit(Request $request, string $template)
    {
        $resource = DeviceTemplate::findOrFail($template);
        $data = $request->validate(['revision_id' => ['nullable', 'integer', 'min:1']]);
        $submission = $this->publication->submit($request->user(), $resource, isset($data['revision_id']) ? (int) $data['revision_id'] : null);
        return response()->json(['data' => $this->publication->submissionData($submission)], 201);
    }

    public function history(Request $request, string $template)
    {
        $data = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        return response()->json($this->history->forResource($request->user(), 'device_template', $template, (int) ($data['per_page'] ?? 20)));
    }

    public function catalog(Request $request)
    {
        abort_unless($request->user()->organization_id, 403);
        $templates = $this->publication->publishedForOrganization((int) $request->user()->organization_id)->with(['organization:id,name', 'publicationState.currentVersion.revision'])->orderBy('name')->paginate(25);
        return response()->json($templates->through(fn (DeviceTemplate $template) => $this->catalogItem($template)));
    }

    public function published(Request $request, string $template)
    {
        abort_unless($request->user()->organization_id, 403);
        $resource = $this->publication->publishedForOrganization((int) $request->user()->organization_id)->with('publicationState.currentVersion.revision')->findOrFail($template);
        return response()->json(['data' => $this->catalogItem($resource, true)]);
    }

    private function catalogItem(DeviceTemplate $template, bool $snapshot = false): array
    {
        $version = $template->publicationState->currentVersion;
        $revision = $version->revision;
        return ['id' => (string) $template->id, 'name' => $revision->snapshot['metadata']['name'] ?? $template->name, 'description' => $revision->snapshot['metadata']['description'] ?? null, 'deviceType' => $revision->snapshot['metadata']['deviceType'] ?? null, 'protocol' => $revision->snapshot['metadata']['protocol'] ?? null, 'currentVersion' => ['id' => (string) $version->id, 'number' => $version->publication_number, 'publishedAt' => $version->published_at?->toISOString()], 'approvedRevision' => ['id' => (string) $revision->id, 'number' => $revision->revision_number], 'parameterCount' => count($revision->snapshot['parameters'] ?? []), 'snapshot' => $snapshot ? $revision->snapshot : null];
    }
}
