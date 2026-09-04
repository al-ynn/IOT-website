<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDisabledResourceService;
use Illuminate\Http\Request;

final class AdminDisabledResourceController extends Controller
{
    public function __construct(private AdminDisabledResourceService $resources) {}
    public function index(Request $request) { return response()->json($this->resources->index($request->user(), $request->validate(['resource_type'=>['nullable','string'],'organization_id'=>['nullable','integer','exists:organizations,id'],'disabled_by'=>['nullable','integer','exists:users,id'],'needs_attention'=>['nullable','boolean'],'has_publication'=>['nullable','boolean'],'state'=>['nullable','in:disabled,archived'],'search'=>['nullable','string','max:100'],'sort'=>['nullable','in:newest,oldest,name'],'per_page'=>['nullable','integer','in:25,50'],'page'=>['nullable','integer','min:1']]))); }
    public function summary(Request $request) { return ['data' => $this->resources->summary($request->user())]; }
}
