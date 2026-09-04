<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Inventory\ResourceInventoryAdapterRegistry;
use App\Services\AdminResourceInventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminResourceInventoryController extends Controller
{
    public function __construct(
        private AdminResourceInventoryService $inventory,
        private ResourceInventoryAdapterRegistry $registry,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'resource_type' => ['nullable', Rule::in($this->registry->types())],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'lifecycle' => ['nullable', Rule::in(['active', 'disabled', 'archived'])],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['label', 'type'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);

        return response()->json($this->inventory->index($request->user(), $filters));
    }

    public function metadata(Request $request): array
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        return ['data' => ['resourceTypes' => $this->inventory->types(), 'lifecycles' => ['active', 'disabled', 'archived']]];
    }

    public function show(Request $request, string $type, string $id): array
    {
        return ['data' => $this->inventory->show($request->user(), $type, $id)];
    }}
