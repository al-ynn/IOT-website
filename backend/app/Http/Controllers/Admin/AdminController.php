<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\Device;use App\Models\Organization;use App\Models\User;use Illuminate\Http\Request;
class AdminController extends Controller{
 public function overview(){return ['organizations'=>Organization::count(),'activeOrganizations'=>Organization::where('status','active')->count(),'users'=>User::count(),'devices'=>Device::count(),'activeAlerts'=>0];}
 public function organizations(){return Organization::with(['users'=>fn($q)=>$q->where('role','owner')])->withCount(['users','devices'])->get()->map(fn($o)=>['id'=>(string)$o->id,'name'=>$o->name,'slug'=>$o->slug,'ownerName'=>$o->users->first()?->name??'','ownerEmail'=>$o->users->first()?->email??'','memberCount'=>$o->users_count,'deviceCount'=>$o->devices_count,'status'=>$o->status,'createdAt'=>$o->created_at->toISOString()]);}
 public function organizationStatus(Request $r,string $organization){$d=$r->validate(['status'=>'required|in:active,suspended']);$o=Organization::findOrFail($organization);$o->update($d);return response()->json(['status'=>$o->status]);}
 public function users(){return User::with('organization')->get()->map(fn($u)=>['id'=>(string)$u->id,'name'=>$u->name,'email'=>$u->email,'organizationId'=>(string)$u->organization_id,'organizationName'=>$u->organization?->name??'','role'=>$u->role,'status'=>$u->status,'createdAt'=>$u->created_at->toISOString()]);}
 public function userStatus(Request $r,string $user){$d=$r->validate(['status'=>'required|in:active,suspended']);$u=User::findOrFail($user);abort_if($u->id===$r->user()->id&&$d['status']==='suspended',422,'Cannot suspend your own account.');$u->update($d);if($u->status==='suspended')$u->tokens()->delete();return response()->json(['status'=>$u->status]);}
}
