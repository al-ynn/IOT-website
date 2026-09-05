<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\Webhook;
use App\Services\ReviewClaimService;
use App\Services\ReviewHistoryService;
use App\Services\WebhookActivationService;
use Illuminate\Http\Request;

final class WebhookActivationReviewController extends Controller
{
    public function __construct(private WebhookActivationService $service, private ReviewClaimService $claims, private ReviewHistoryService $history) {}

    public function show(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($r, $s);

        return ['data' => $this->data($s)];
    }

    public function history(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($r, $s);

        return ['data' => $this->history->forSubmission($r->user(), $s)];
    }

    public function claim(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($r, $s);

        return ['data' => $this->data($this->claims->claim($r->user(), $s))];
    }

    public function release(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($r, $s);

        return ['data' => $this->data($this->claims->release($r->user(), $s))];
    }

    public function takeover(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($r, $s);
        $d = $r->validate(['confirm' => 'required|accepted']);

        return ['data' => $this->data($this->claims->takeover($r->user(), $s, true))];
    }

    public function approve(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($r, $s);
        $d = $r->validate(['confirm_active_change' => 'sometimes|boolean']);

        return ['data' => $this->data($this->service->approve($r->user(), $s, (bool) ($d['confirm_active_change'] ?? false)))];
    }

    public function requestChanges(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($r, $s);
        $d = $r->validate(['note' => 'required|string|max:1000']);

        return ['data' => $this->data($this->service->decide($r->user(), $s, 'changes_requested', $d['note']))];
    }

    public function reject(Request $r, ResourcePublicationSubmission $s)
    {
        $this->assert($r, $s);
        $d = $r->validate(['note' => 'required|string|max:1000']);

        return ['data' => $this->data($this->service->decide($r->user(), $s, 'rejected', $d['note']))];
    }

    private function assert(Request $r, $s): void
    {
        abort_unless($s->resource_type === 'webhook' && Webhook::whereKey($s->resource_id)->where('organization_id', $r->user()->organization_id)->exists(), 404);
    }

    private function data($s): array
    {
        $latest = ResourceRevision::where(['resource_type' => 'webhook', 'resource_id' => $s->resource_id])->latest('revision_number')->first();

        return ['id' => (string) $s->id, 'status' => $s->status, 'revisionId' => (string) $s->submitted_revision_id, 'newerRevisionExists' => $latest && $latest->id !== $s->submitted_revision_id, 'reviewerId' => $s->reviewer_id ? (string) $s->reviewer_id : null];
    }
}
