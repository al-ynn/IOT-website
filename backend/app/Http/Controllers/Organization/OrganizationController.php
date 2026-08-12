<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function create(Request $request){abort_if($request->user()->organization_id,409,'User already belongs to an organization.');$data=$request->validate(['name'=>'required|string|max:255']);$org=\App\Models\Organization::create(['name'=>$data['name'],'slug'=>Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);$request->user()->update(['organization_id'=>$org->id,'role'=>'owner']);$plan=\App\Models\Plan::where('is_default',true)->where('active',true)->firstOrFail();$org->subscriptions()->create(['plan_id'=>$plan->id,'status'=>'active','current_period_start'=>now()]);return response()->json($this->show($request),201);}
    public function show(Request $request) { $org=$request->user()->organization; abort_unless($org,404,'Organization not found.'); return ['id'=>(string)$org->id,'name'=>$org->name,'slug'=>$org->slug,'createdAt'=>$org->created_at?->toISOString()]; }
    public function permissions(Request $request) { $all=['device.view','device.manage','dashboard.view','dashboard.manage','billing.view','billing.manage','organization.manage','members.manage','audit.view','automation.view','automation.create','automation.update','automation.delete','automation.execute','automation.manage','analytics.view'];$allowed=array_values(array_filter($all,fn($p)=>$request->user()->hasOrganizationPermission($p)));return array_map(fn($name)=>['id'=>$name,'name'=>$name,'description'=>'','category'=>strstr($name,'.',true)],$allowed); }
    public function update(Request $request){abort_unless($request->user()->hasOrganizationPermission('organization.manage'),403);$org=$request->user()->organization;$org->update($request->validate(['name'=>'sometimes|required|string|max:255','description'=>'nullable|string|max:1000']));return $this->show($request);}
    public function settings(Request $request){$org=$request->user()->organization;abort_unless($org,403);return ['organizationId'=>(string)$org->id,...array_merge(['timezone'=>'UTC','language'=>'en','emailNotifications'=>true],$org->settings??[])];}
    public function updateSettings(Request $request){abort_unless($request->user()->hasOrganizationPermission('organization.manage'),403);$data=$request->validate(['timezone'=>'required|string','language'=>'required|string','emailNotifications'=>'required|boolean']);$request->user()->organization->update(['settings'=>$data]);return ['organizationId'=>(string)$request->user()->organization_id,...$data];}
    public function members(Request $request){abort_unless($request->user()->organization,403);return $request->user()->organization->users()->get()->map(fn($u)=>['id'=>(string)$u->id,'name'=>$u->name,'email'=>$u->email,'role'=>$u->role,'joinedAt'=>$u->created_at->toISOString()]);}
}
