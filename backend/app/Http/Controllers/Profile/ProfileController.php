<?php
namespace App\Http\Controllers\Profile;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Illuminate\Support\Facades\Hash;
class ProfileController extends Controller{
 public function show(Request $r){$u=$r->user();return ['id'=>(string)$u->id,'name'=>$u->name,'email'=>$u->email,'role'=>$u->role,'organizationId'=>(string)$u->organization_id,'createdAt'=>$u->created_at->toISOString()];}
 public function update(Request $r){$u=$r->user();$u->update($r->validate(['name'=>'sometimes|required|string|max:255','email'=>'sometimes|required|email|unique:users,email,'.$u->id]));return $this->show($r);}
 public function password(Request $r){$d=$r->validate(['currentPassword'=>'required|string','newPassword'=>'required|string|min:8']);abort_unless(Hash::check($d['currentPassword'],$r->user()->password),422,'Current password is incorrect.');$r->user()->update(['password'=>$d['newPassword']]);$r->user()->tokens()->delete();return response()->noContent();}
 public function notifications(Request $r){return $r->user()->notification_settings??['emailAlerts'=>true,'deviceAlerts'=>true,'weeklyReports'=>false];}
 public function updateNotifications(Request $r){$d=$r->validate(['emailAlerts'=>'required|boolean','deviceAlerts'=>'required|boolean','weeklyReports'=>'required|boolean']);$r->user()->update(['notification_settings'=>$d]);return $d;}
}
