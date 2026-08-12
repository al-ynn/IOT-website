<?php
namespace App\Http\Controllers\Organization;
use App\Http\Controllers\Controller;use App\Models\Invitation;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Illuminate\Support\Str;
class InvitationController extends Controller{
 public function index(Request $r){$this->manage($r);return $r->user()->organization->invitations()->latest()->get()->map(fn($i)=>$this->resource($i));}
 public function store(Request $r){$this->manage($r);$d=$r->validate(['email'=>'required|email','role'=>'required|in:admin,engineer,operator,viewer']);abort_if($r->user()->organization->users()->where('email',$d['email'])->exists(),422,'User is already a member.');$token=Str::random(64);$i=$r->user()->organization->invitations()->updateOrCreate(['email'=>$d['email'],'status'=>'pending'],['role'=>$d['role'],'invited_by'=>$r->user()->id,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDays(7)]);return response()->json([...$this->resource($i),'token'=>$token],201);}
 public function destroy(Request $r,string $invitation){$this->manage($r);$i=$r->user()->organization->invitations()->findOrFail($invitation);$i->update(['status'=>'cancelled']);return response()->noContent();}
 public function accept(Request $r){$d=$r->validate(['token'=>'required|string']);$i=Invitation::where('token_hash',hash('sha256',$d['token']))->where('status','pending')->where('expires_at','>',now())->firstOrFail();abort_if($r->user()->organization_id&&$r->user()->organization_id!==$i->organization_id,403);DB::transaction(function()use($r,$i){$r->user()->update(['organization_id'=>$i->organization_id,'role'=>$i->role]);$i->update(['status'=>'accepted']);});return response()->json(['organizationId'=>(string)$i->organization_id]);}
 private function manage(Request $r){abort_unless($r->user()->organization&&$r->user()->hasOrganizationPermission('members.manage'),403);}
 private function resource(Invitation $i):array{return ['id'=>(string)$i->id,'organizationId'=>(string)$i->organization_id,'email'=>$i->email,'role'=>$i->role,'status'=>$i->status,'invitedBy'=>(string)$i->invited_by,'createdAt'=>$i->created_at->toISOString(),'expiresAt'=>$i->expires_at->toISOString()];}
}
