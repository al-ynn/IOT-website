<?php

namespace App\Http\Controllers;

use App\Services\ResourceRevisionStateService;
use App\Services\RevisionComparisonService;
use Illuminate\Http\Request;

final class PullUpdateController extends Controller
{
    public function __construct(private ResourceRevisionStateService $states, private RevisionComparisonService $comparisons) {}

    public function state(Request $r, string $type, string $resource)
    {
        return $this->states->state($r->user(), $type, $resource);
    }

    public function review(Request $r, string $type, string $resource)
    {
        return $this->comparisons->acceptedToLatest($r->user(), $type, $resource);
    }

    public function pull(Request $r, string $type, string $resource)
    {
        $r->validate(['user_id' => ['prohibited'], 'target_revision_id' => ['prohibited']]);

        return $this->states->pull($r->user(), $type, $resource);
    }

    public function ignore(Request $r, string $type, string $resource)
    {
        return $this->states->ignore($r->user(), $type, $resource);
    }

    public function changes(Request $r)
    {
        $data = $r->validate(['per_page' => ['nullable', 'integer', 'in:20,25,50'], 'resource_type' => ['nullable', 'in:device'], 'lifecycle' => ['nullable', 'in:active,disabled,archived'], 'q' => ['nullable', 'string', 'max:100'], 'user_id' => ['prohibited'], 'organization_id' => ['prohibited']]);

        return $this->states->changes($r->user(),$data);
    }
}
