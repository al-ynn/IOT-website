<?php

namespace App\Http\Controllers;

use App\Services\ResourceAttentionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ResourceAttentionController extends Controller
{
    public function __construct(private ResourceAttentionService $attention) {}
    public function index(Request $request) { $data=$request->validate(['resource_type'=>['nullable','string'],'reason'=>['nullable','string'],'organization_id'=>['nullable','integer','exists:organizations,id'],'marked_by'=>['nullable','integer','exists:users,id'],'search'=>['nullable','string','max:100'],'sort'=>['nullable',Rule::in(['newest','oldest'])],'per_page'=>['nullable','integer',Rule::in([25,50])],'page'=>['nullable','integer','min:1']]);return response()->json($this->attention->queue($request->user(),$data)); }
    public function count(Request $request) { return ['count'=>$this->attention->count($request->user())]; }
    public function show(Request $request,string $type,int $id) { return response()->json(['data'=>$this->attention->show($request->user(),$type,$id)]); }
    public function mark(Request $request,string $type,int $id) { $data=$this->payload($request);return response()->json(['data'=>$this->attention->mark($request->user(),$type,$id,$data['reason_code'],$data['note'])],201); }
    public function update(Request $request,string $type,int $id) { $data=$this->payload($request);return response()->json(['data'=>$this->attention->update($request->user(),$type,$id,$data['reason_code'],$data['note'])]); }
    public function clear(Request $request,string $type,int $id) { return response()->json(['data'=>$this->attention->clear($request->user(),$type,$id)]); }
    public function myWork(Request $request) { $data=$request->validate(['resource_type'=>['nullable','string'],'per_page'=>['nullable','integer',Rule::in([20,50])],'page'=>['nullable','integer','min:1']]);return response()->json($this->attention->myWork($request->user(),$data)); }
    private function payload(Request $request): array { return $request->validate(['reason_code'=>['required','string'],'note'=>['required','string','max:1000']]); }
}
