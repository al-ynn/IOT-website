<?php
namespace App\Http\Controllers;
use App\Models\Dashboard;use App\Services\Billing\BillingEntitlementService;use App\Services\Billing\BillingLimitService;use Illuminate\Http\Request;
class DashboardController extends Controller
{
 public function index(Request $r){$this->view($r);return $r->user()->organization->dashboards()->get()->map(fn($d)=>$this->resource($d));}
 public function show(Request $r,string $dashboard){$this->view($r);return $this->resource($r->user()->organization->dashboards()->findOrFail($dashboard));}
 public function store(Request $r,BillingEntitlementService $e,BillingLimitService $l){$org=$this->manage($r);$e->requireFeature($org,'dashboard.customize');$l->ensureDashboardAvailable($org);$d=$org->dashboards()->create($this->validated($r));return response()->json($this->resource($d),201);}
 public function update(Request $r,string $dashboard){$org=$this->manage($r);$d=$org->dashboards()->findOrFail($dashboard);$d->update($this->validated($r));return $this->resource($d->refresh());}
 public function destroy(Request $r,string $dashboard){$this->manage($r)->dashboards()->findOrFail($dashboard)->delete();return response()->noContent();}
 public function duplicate(Request $r,string $dashboard,BillingEntitlementService $e,BillingLimitService $l){$org=$this->manage($r);$e->requireFeature($org,'dashboard.customize');$l->ensureDashboardAvailable($org);$source=$org->dashboards()->findOrFail($dashboard);$copy=$source->replicate();$copy->name=$source->name.' Copy';$copy->save();return response()->json($this->resource($copy),201);}
 private function validated(Request $r):array{$v=$r->validate(['name'=>'required|string|max:255','description'=>'nullable|string|max:1000','widgets'=>'present|array']);return ['name'=>$v['name'],'description'=>$v['description']??null,'configuration'=>['widgets'=>$v['widgets']]];}
 private function view(Request $r){abort_unless($r->user()->organization&&$r->user()->hasOrganizationPermission('dashboard.view'),403);}
 private function manage(Request $r){abort_unless($r->user()->organization&&$r->user()->hasOrganizationPermission('dashboard.manage'),403);return $r->user()->organization;}
 private function resource(Dashboard $d):array{return ['id'=>(string)$d->id,'name'=>$d->name,'description'=>$d->description,'widgets'=>$d->configuration['widgets']??[]];}
}
