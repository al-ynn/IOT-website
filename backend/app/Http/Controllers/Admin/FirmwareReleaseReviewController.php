<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FirmwareArtifact;
use App\Models\ResourcePublicationSubmission;
use App\Services\FirmwareReleaseService;
use App\Services\ReviewClaimService;
use App\Services\ReviewHistoryService;
use Illuminate\Http\Request;

final class FirmwareReleaseReviewController extends Controller
{
    public function __construct(private FirmwareReleaseService $releases, private ReviewClaimService $claims, private ReviewHistoryService $history) {}

    private function firmware(Request $request, ResourcePublicationSubmission $submission): void
    {
        abort_unless($submission->resource_type === 'firmware' && FirmwareArtifact::whereKey($submission->resource_id)->where('organization_id', $request->user()->organization_id)->exists(), 404);
    }

    public function show(Request $request, ResourcePublicationSubmission $submission)
    {
        $this->firmware($request, $submission);

        return ['data' => $this->releases->data($submission->load(['revision', 'submitter:id,name', 'reviewer:id,name', 'decisionMaker:id,name']))];
    }

    public function history(Request $request, ResourcePublicationSubmission $submission)
    {
        $this->firmware($request, $submission);

        return ['data' => $this->history->forSubmission($request->user(), $submission)];
    }

    public function approve(Request $request, ResourcePublicationSubmission $submission)
    {
        $this->firmware($request, $submission);

        return ['data' => $this->releases->data($this->releases->decide($request->user(), $submission, 'approved'))];
    }

    public function requestChanges(Request $request, ResourcePublicationSubmission $submission)
    {
        $this->firmware($request, $submission);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        return ['data' => $this->releases->data($this->releases->decide($request->user(), $submission, 'changes_requested', $data['note']))];
    }

    public function reject(Request $request, ResourcePublicationSubmission $submission)
    {
        $this->firmware($request, $submission);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        return ['data' => $this->releases->data($this->releases->decide($request->user(), $submission, 'rejected', $data['note']))];
    }

    public function claim(Request $request, ResourcePublicationSubmission $submission)
    {
        $this->firmware($request, $submission);

        return ['data' => $this->releases->data($this->claims->claim($request->user(), $submission))];
    }

    public function release(Request $request, ResourcePublicationSubmission $submission)
    {
        $this->firmware($request, $submission);

        return ['data' => $this->releases->data($this->claims->release($request->user(), $submission))];
    }

    public function takeover(Request $request, ResourcePublicationSubmission $submission)
    {
        $this->firmware($request, $submission);
        $data = $request->validate(['confirm' => ['required', 'accepted']]);

        return ['data' => $this->releases->data($this->claims->takeover($request->user(),$submission,(bool) $data['confirm']))];
    }
}
