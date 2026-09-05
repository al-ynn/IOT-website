<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Services\ReportPublicationService;
use App\Services\ReviewClaimService;
use App\Services\ReviewHistoryService;
use Illuminate\Http\Request;

final class ReportPublicationReviewController extends Controller
{
    public function __construct(private ReportPublicationService $service, private ReviewClaimService $claims, private ReviewHistoryService $history) {}

    public function index(Request $request)
    {
        return response()->json(ResourcePublicationSubmission::where('resource_type', 'report')
            ->whereIn('resource_id', Report::where('organization_id', $request->user()->organization_id)->select('id'))
            ->with(['revision', 'submitter:id,name', 'reviewer:id,name'])->latest('submitted_at')->paginate(25)->through(fn ($s) => $this->data($s)));
    }

    public function show(ResourcePublicationSubmission $submission)
    {
        $this->assert($submission);

        return response()->json(['data' => $this->data($submission)]);
    }

    public function history(Request $r, ResourcePublicationSubmission $submission)
    {
        $this->assert($submission);

        return response()->json(['data' => $this->history->forSubmission($r->user(), $submission)]);
    }

    public function claim(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($s);

        return response()->json(['data' => $this->data($this->claims->claim($r->user(), $s))]);
    }

    public function release(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($s);

        return response()->json(['data' => $this->data($this->claims->release($r->user(), $s))]);
    }

    public function takeover(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($s);
        $d = $r->validate(['confirm' => 'required|accepted']);

        return response()->json(['data' => $this->data($this->claims->takeover($r->user(), $s, (bool) $d['confirm']))]);
    }

    public function approve(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($s);
        $d = $r->validate(['confirm_older_revision' => 'sometimes|boolean']);

        return response()->json(['data' => $this->data($this->service->approve($r->user(), $s, (bool) ($d['confirm_older_revision'] ?? false)))]);
    }

    public function requestChanges(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($s);
        $d = $r->validate(['note' => 'required|string|max:1000']);

        return response()->json(['data' => $this->data($this->service->requestChanges($r->user(), $s, $d['note']))]);
    }

    public function reject(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($s);
        $d = $r->validate(['note' => 'required|string|max:1000']);

        return response()->json(['data' => $this->data($this->service->reject($r->user(), $s, $d['note']))]);
    }

    private function assert($s): void
    {
        abort_unless($s->resource_type === 'report'
            && Report::whereKey($s->resource_id)->where('organization_id', request()->user()->organization_id)->exists(), 404);
    }

    private function data($s): array
    {
        $latest = ResourceRevision::where(['resource_type' => 'report', 'resource_id' => $s->resource_id])->latest('revision_number')->first();

        return $this->service->submissionData($s->loadMissing(['revision', 'submitter:id,name', 'reviewer:id,name']),$latest);
    }
}
