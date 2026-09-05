<?php
namespace App\Http\Controllers;
use App\Models\FirmwareArtifact;use App\Services\FirmwareReleaseService;use Illuminate\Http\Request;
final class FirmwareReleaseController extends Controller
{
 public function __construct(private FirmwareReleaseService $releases){}
 public function state(Request $request,FirmwareArtifact $artifact){return ['data'=>$this->releases->state($request->user(),$artifact)];}
 public function submit(Request $request,FirmwareArtifact $artifact){return response()->json(['data'=>$this->releases->data($this->releases->submit($request->user(),$artifact))],201);}
 public function history(Request $request,FirmwareArtifact $artifact){return response()->json($this->releases->history($request->user(),$artifact));}
}
