<?php

namespace App\Http\Controllers;

use App\Services\CrossDomainSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CrossDomainSearchController
{
    public function __invoke(Request $request, CrossDomainSearchService $search): JsonResponse
    {
        $allowed = ['q', 'resource_type', 'lifecycle', 'per_page', 'page'];
        if (array_diff(array_keys($request->query()), $allowed)) {
            return response()->json(['message' => 'Unsupported search parameter.'], 422);
        }
        $filters = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'resource_type' => ['sometimes', 'string', Rule::in(['device', 'device_template', 'dashboard', 'automation', 'report', 'location', 'firmware', 'webhook'])],
            'lifecycle' => ['sometimes', 'string', Rule::in(['active', 'disabled', 'archived'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->json($search->paginate($request->user(), $filters));
    }
}
