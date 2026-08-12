<?php
namespace App\Http\Controllers\Automation;
use App\Http\Controllers\Controller;use App\Models\AutomationExecution;use App\Services\Billing\BillingEntitlementService;use Illuminate\Http\Request;
class AutomationLogController extends Controller{
 public function __construct(private BillingEntitlementService $billing){}
 public function index(Request $r){$org=$this->org($r);return AutomationExecution::where('organization_id',$org->id)->with(['automation:id,name','logs'])->latest()->paginate(min((int)$r->input('perPage',20),100))->through(fn($e)=>$this->resource($e));}
 public function show(Request $r,string $execution){$org=$this->org($r);return $this->resource(AutomationExecution::where('organization_id',$org->id)->with(['automation:id,name','logs'])->findOrFail($execution));}
 private function org(Request $r){abort_unless($r->user()->organization&&$r->user()->hasOrganizationPermission('automation.view'),403);$this->billing->requireFeature($r->user()->organization,'automation.basic');return $r->user()->organization;}
 private function resource($e):array{return ['id'=>(string)$e->id,'automationId'=>(string)$e->automation_id,'automationName'=>$e->automation?->name??'Deleted automation','status'=>match($e->status){'completed'=>'success','pending','running'=>'running',default=>'failed'},'triggerData'=>$e->trigger_data??[],'actions'=>$e->result_summary['actions']??[],'error'=>$e->error_message,'executedAt'=>($e->started_at??$e->created_at)->toISOString(),'duration'=>$e->started_at&&$e->completed_at?$e->started_at->diffInMilliseconds($e->completed_at):null];}
}
