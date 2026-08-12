<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\PaymentEvent;
use App\Models\Plan;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function plans() { return Plan::where('active',true)->get()->map(fn($p)=>$this->plan($p)); }
    public function subscription(Request $request) { $s=$request->user()->organization?->subscription()->with('plan')->first(); return $s ? ['id'=>(string)$s->id,'organizationId'=>(string)$s->organization_id,'planId'=>$s->plan_id,'status'=>$s->status,'currentPeriodStart'=>$s->current_period_start?->toISOString() ?? $s->created_at->toISOString(),'currentPeriodEnd'=>$s->plan?->interval==='lifetime' ? null : $s->current_period_end?->toISOString(),'cancelAtPeriodEnd'=>$s->plan?->interval==='lifetime' ? false : $s->cancel_at_period_end,'createdAt'=>$s->created_at->toISOString()] : response()->json(null); }
    public function entitlements(Request $request) { $s=$request->user()->organization?->subscription()->with('plan')->first(); $p=$s?->plan; return ['planId'=>$p?->id,'features'=>$p?->features??[],'deviceLimit'=>$p?->device_limit??0,'userLimit'=>$p?->user_limit??0,'dashboardLimit'=>$p?->dashboard_limit??0,'automationLimit'=>$p?->automation_limit??0]; }
    public function payments(Request $request) { $id=$request->user()->organization_id; return PaymentEvent::where('organization_id',$id)->whereNotNull('transaction_id')->latest()->get()->map(fn($e)=>['id'=>(string)$e->id,'organizationId'=>(string)$e->organization_id,'transactionId'=>$e->transaction_id,'planId'=>$e->plan_id,'amount'=>(float)($e->amount??0),'currency'=>$e->currency??'USD','status'=>$e->status,'paidAt'=>$e->processed_at?->toISOString(),'createdAt'=>$e->created_at->toISOString()]); }
    public function cancel(Request $request) { abort_unless($request->user()->hasOrganizationPermission('billing.manage'),403);$s=$request->user()->organization?->subscription()->with('plan')->firstOrFail(); abort_if($s->plan->interval==='lifetime',422,'Lifetime plans cannot be cancelled.'); $s->update(['cancel_at_period_end'=>true]); return response()->json(['cancelAtPeriodEnd'=>true]); }
    public function resume(Request $request) { abort_unless($request->user()->hasOrganizationPermission('billing.manage'),403);$s=$request->user()->organization?->subscription()->with('plan')->firstOrFail(); abort_if($s->plan->interval==='lifetime',422,'Lifetime plans do not renew.'); $s->update(['cancel_at_period_end'=>false]); return response()->json(['cancelAtPeriodEnd'=>false]); }
    public function activateFree(Request $request) { abort_unless($request->user()->hasOrganizationPermission('billing.manage'),403);$plan=Plan::where('is_default',true)->where('active',true)->where('price',0)->firstOrFail();$org=$request->user()->organization;return \Illuminate\Support\Facades\DB::transaction(function()use($org,$plan){$org->subscriptions()->whereIn('status',['active','trialing','past_due'])->update(['status'=>'cancelled']);$s=$org->subscriptions()->create(['plan_id'=>$plan->id,'status'=>'active','current_period_start'=>now()]);return response()->json(['id'=>(string)$s->id,'planId'=>$s->plan_id,'status'=>$s->status],201);}); }
    public function checkout(Request $request) { $request->validate(['planId'=>'required|exists:plans,id']); return response()->json(['message'=>'No payment provider is configured.','code'=>'BILLING_PROVIDER_NOT_CONFIGURED'],503); }
    private function plan(Plan $p): array { return ['id'=>$p->id,'name'=>$p->name,'description'=>$p->description??'','price'=>(float)$p->price,'currency'=>$p->currency,'interval'=>$p->interval,'features'=>$p->features,'deviceLimit'=>$p->device_limit,'userLimit'=>$p->user_limit,'dashboardLimit'=>$p->dashboard_limit,'automationLimit'=>$p->automation_limit,'active'=>$p->active]; }
}
