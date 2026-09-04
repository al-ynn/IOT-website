<?php

namespace App\Http\Controllers;

use App\Services\SharedWithMeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SharedWithMeController extends Controller
{
    public function __invoke(Request $request, SharedWithMeService $service)
    {
        $allowed = ['resource_type', 'lifecycle', 'permission', 'q', 'per_page', 'page'];
        if (array_diff(array_keys($request->query()), $allowed)) {
            return response()->json(['message' => 'Unsupported Shared With Me parameter.'], 422);
        }

        $filters = $request->validate([
            'resource_type' => ['nullable', Rule::in(['device', 'device_template', 'dashboard', 'automation', 'report', 'location', 'firmware', 'webhook'])],
            'lifecycle' => ['nullable', Rule::in(['active', 'disabled', 'archived'])],
            'permission' => ['nullable', Rule::in(['viewer', 'full_access', 'view', 'edit'])],
            'q' => ['nullable', 'string', 'min:2', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($service->paginate($request->user(), $filters));
    }
}
