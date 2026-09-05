<?php

namespace App\Http\Controllers;

use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationVersion;
use App\Services\ResourcePublicationResolver;
use Illuminate\Http\Request;

final class TemplatePublicationVersionController extends Controller
{
    public function __construct(private ResourcePublicationResolver $resolver) {}

    public function index(Request $request, string $template)
    {
        [$resource, $current] = $this->resolver->publishedTemplate($request->user(), $template);
        $versions = ResourcePublicationVersion::where(['resource_type' => 'device_template', 'resource_id' => $resource->id])
            ->with(['revision:id,revision_number', 'publisher:id,name'])->latest('publication_number')->paginate(20)
            ->through(fn ($version) => $this->summary($version, (int) $current->id));
        return response()->json($versions);
    }

    public function show(Request $request, string $template, string $version)
    {
        [, $resolved] = $this->resolver->version($request->user(), $template, $version);
        $currentId = ResourcePublicationState::where(['resource_type' => 'device_template', 'resource_id' => $template])->value('current_publication_version_id');
        return response()->json(['data' => $this->summary($resolved, (int) $currentId) + ['snapshot' => $resolved->revision->snapshot]]);
    }

    private function summary(ResourcePublicationVersion $version, int $currentId): array
    {
        return [
            'id' => (string) $version->id,
            'number' => $version->publication_number,
            'current' => (int) $version->id === $currentId,
            'revision' => ['id' => (string) $version->revision->id, 'number' => $version->revision->revision_number],
            'publishedBy' => $version->publisher ? ['id' => (string) $version->publisher->id, 'name' => $version->publisher->name] : null,
            'publishedAt' => $version->published_at?->toISOString(),
            'previousVersionId' => $version->previous_publication_version_id ? (string) $version->previous_publication_version_id : null,
            'reviewSubmissionId' => $version->review_submission_id ? (string) $version->review_submission_id : null,
            'readOnly' => true,
        ];
    }
}
