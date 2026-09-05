<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceTemplate;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Services\TemplatePublicationService;
use App\Services\ReviewClaimService;
use App\Services\ReviewHistoryService;
use Illuminate\Http\Request;

final class TemplatePublicationReviewController extends Controller
{
    public function __construct(private TemplatePublicationService $publication, private ReviewClaimService $claims, private ReviewHistoryService $history) {}

    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', 'in:submitted,approved,changes_requested,rejected'], 'organization_id' => ['nullable', 'integer', 'exists:organizations,id']]);
        $query = ResourcePublicationSubmission::where('resource_type', 'device_template')->with(['revision', 'submitter:id,name', 'decisionMaker:id,name', 'reviewer:id,name'])->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $query->whereIn('resource_id', DeviceTemplate::where('organization_id', $request->user()->organization_id)->select('id'));
        return response()->json($query->latest('submitted_at')->paginate(25)->through(fn ($submission) => $this->payload($submission)));
    }

    public function show(ResourcePublicationSubmission $submission)
    {
        $this->assertCurrentOrganization($submission);
        return response()->json(['data' => $this->payload($submission->load(['revision', 'submitter:id,name', 'decisionMaker:id,name', 'reviewer:id,name']))]);
    }

    public function history(Request $request, ResourcePublicationSubmission $submission)
    {
        $this->assertCurrentOrganization($submission);
        return response()->json(['data' => $this->history->forSubmission($request->user(), $submission)]);
    }

    public function approve(Request $request, ResourcePublicationSubmission $submission)
    {
        $data = $request->validate(['confirm_older_revision' => ['sometimes', 'boolean']]);
        return response()->json(['data' => $this->payload($this->publication->approve($request->user(), $submission, (bool) ($data['confirm_older_revision'] ?? false)))]);
    }

    public function requestChanges(Request $request, ResourcePublicationSubmission $submission)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        return response()->json(['data' => $this->payload($this->publication->requestChanges($request->user(), $submission, $data['note']))]);
    }

    public function reject(Request $request, ResourcePublicationSubmission $submission)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        return response()->json(['data' => $this->payload($this->publication->reject($request->user(), $submission, $data['note']))]);
    }

    public function claim(Request $request, ResourcePublicationSubmission $submission)
    {
        return response()->json(['data' => $this->payload($this->claims->claim($request->user(), $submission))]);
    }

    public function release(Request $request, ResourcePublicationSubmission $submission)
    {
        return response()->json(['data' => $this->payload($this->claims->release($request->user(), $submission))]);
    }

    public function takeover(Request $request, ResourcePublicationSubmission $submission)
    {
        $data = $request->validate(['confirm' => ['required', 'accepted']]);
        return response()->json(['data' => $this->payload($this->claims->takeover($request->user(), $submission, (bool) $data['confirm']))]);
    }

    private function payload(ResourcePublicationSubmission $submission): array
    {
        $latest = ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $submission->resource_id])->latest('revision_number')->first();
        return $this->publication->submissionData($submission->loadMissing(['revision', 'submitter:id,name', 'decisionMaker:id,name']), $latest);
    }

    private function assertCurrentOrganization(ResourcePublicationSubmission $submission): void
    {
        abort_unless($submission->resource_type === 'device_template'
            && DeviceTemplate::whereKey($submission->resource_id)->where('organization_id', request()->user()->organization_id)->exists(), 404);
    }
}
