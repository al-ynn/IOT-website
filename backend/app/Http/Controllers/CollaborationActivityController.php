<?php

namespace App\Http\Controllers;

use App\Services\CollaborationActivityRegistry;
use App\Services\CollaborationActivityService;
use Illuminate\Http\Request;

final class CollaborationActivityController extends Controller
{
    public function __construct(private CollaborationActivityService $activity, private CollaborationActivityRegistry $registry) {}

    public function index(Request $request)
    {
        if (array_diff(array_keys($request->query()), ['category','resource_type','section','date_from','date_to','page','per_page'])) return response()->json(['message' => 'Unsupported Activity parameter.'], 422);
        $filters = $request->validate([
            'category' => ['nullable', 'string'], 'resource_type' => ['nullable', 'string'], 'section' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        return response()->json($this->activity->paginate($request->user(), $this->registry->validateFilters($filters)));
    }

    public function resource(Request $request, string $type, int $resource)
    {
        if (array_diff(array_keys($request->query()), ['section','page','per_page'])) return response()->json(['message' => 'Unsupported resource Activity parameter.'], 422);
        $filters = $request->validate(['section' => ['nullable', 'string', 'max:50'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $filters['resource_type'] = $type; $filters['resource_id'] = $resource;
        return response()->json($this->activity->paginate($request->user(), $this->registry->validateFilters($filters)));
    }

    public function metadata() { return response()->json($this->registry->metadata()); }
}
