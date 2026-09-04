<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminRecentResourceService;
use Illuminate\Http\Request;

final class AdminRecentResourceController extends Controller
{
    public function __construct(private AdminRecentResourceService $recent) {}
    public function created(Request $request) { return response()->json($this->recent->created($request->user(), $this->filters($request))); }
    public function updated(Request $request) { return response()->json($this->recent->updated($request->user(), $this->filters($request))); }
    private function filters(Request $request): array { return $request->validate(['resource_type' => ['nullable','string'], 'organization_id' => ['nullable','integer','exists:organizations,id'], 'lifecycle' => ['nullable','in:active,disabled,archived'], 'window' => ['nullable','in:24h,7d,30d,90d,all'], 'search' => ['nullable','string','max:100'], 'sort' => ['nullable','in:newest,oldest'], 'per_page' => ['nullable','integer','in:25,50'], 'page' => ['nullable','integer','min:1']]); }
}
