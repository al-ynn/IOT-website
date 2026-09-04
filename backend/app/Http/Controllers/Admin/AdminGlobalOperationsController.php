<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminGlobalOperationsService;
use Illuminate\Http\Request;

class AdminGlobalOperationsController extends Controller
{
    public function overview(Request $request, AdminGlobalOperationsService $service)
    {
        $filters = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'status' => ['nullable', 'in:online,offline'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $filters['organization_id'] = $request->user()->organization_id;

        return response()->json($service->overview($filters));
    }
}
