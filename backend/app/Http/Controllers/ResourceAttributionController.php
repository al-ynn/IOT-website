<?php
namespace App\Http\Controllers;
use App\Services\ResourceAttributionService;use Illuminate\Http\Request;
final class ResourceAttributionController extends Controller{public function __construct(private ResourceAttributionService$attribution){}public function show(Request$request,string$type,int$resource){return response()->json($this->attribution->forResource($request->user(),$type,$resource));}}