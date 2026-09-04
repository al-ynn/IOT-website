<?php

namespace App\Http\Controllers;

use App\Services\CollaborationOverviewService;
use Illuminate\Http\Request;

final class CollaborationOverviewController extends Controller
{
    public function __invoke(Request $request, CollaborationOverviewService $overview)
    {
        if ($request->query()) return response()->json(['message'=>'Collaboration Overview does not accept scope or provider parameters.'],422);
        return response()->json($overview->forUser($request->user()));
    }
}
