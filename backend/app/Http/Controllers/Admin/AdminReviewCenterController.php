<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminReviewCenterService;
use App\Models\ResourcePublicationSubmission;
use Illuminate\Http\Request;

final class AdminReviewCenterController extends Controller
{
    public function __construct(private AdminReviewCenterService $reviews) {}

    public function summary(Request $request)
    {
        $data = $request->validate(['organization_id' => ['nullable', 'integer', 'exists:organizations,id'], 'search' => ['nullable', 'string', 'max:100']]);
        return response()->json(['data' => $this->reviews->summary($request->user(), $data)]);
    }

    public function index(Request $request)
    {
        $data = $request->validate(['state' => ['nullable', 'string'], 'resource_type' => ['nullable', 'string'], 'organization_id' => ['nullable', 'integer', 'exists:organizations,id'], 'search' => ['nullable', 'string', 'max:100'], 'sort' => ['nullable', 'in:oldest,newest'], 'per_page' => ['nullable', 'integer', 'in:25,50'], 'page' => ['nullable', 'integer', 'min:1']]);
        return response()->json($this->reviews->list($request->user(), $data));
    }

    public function show(Request $request, string $resourceType, ResourcePublicationSubmission $submission)
    {
        return response()->json([
            'data' => $this->reviews->detail(
                $request->user(),
                $resourceType,
                $submission,
            ),
        ]);
    }
}
