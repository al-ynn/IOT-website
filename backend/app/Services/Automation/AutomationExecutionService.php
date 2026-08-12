<?php
namespace App\Services\Automation;
use App\Models\Automation;use App\Models\AutomationExecution;use App\Services\Billing\BillingEntitlementService;use Illuminate\Support\Str;
class AutomationExecutionService{
 public function __construct(private BillingEntitlementService $billing,private ConditionEvaluator $conditions,private AutomationActionExecutor $actions,private AutomationDefinitionValidator $validator,private AutomationDefinitionService $definitions){}
 public function execute(Automation $automation,string $triggerType,array $data=[],?string $correlationId=null):AutomationExecution{
  $automation->loadMissing(['organization.users','conditions','actions','triggers','schedules']);$this->billing->requireFeature($automation->organization,'automation.basic');if($this->validator->requiresAdvanced($this->definitions->definition($automation)))$this->billing->requireFeature($automation->organization,'automation.advanced');$correlationId??=(string)Str::uuid();
  $execution=AutomationExecution::firstOrCreate(['correlation_id'=>$correlationId],['automation_id'=>$automation->id,'organization_id'=>$automation->organization_id,'trigger_type'=>$triggerType,'trigger_data'=>$this->redact($data),'status'=>'pending']);
  if(!$execution->wasRecentlyCreated)return $execution;if(!$automation->enabled||$automation->status!=='active')return $this->finish($execution,'skipped',[],'Automation is disabled.');$execution->update(['status'=>'running','started_at'=>now()]);
  try{$group=['logic'=>$automation->conditions->first()?->logic??'AND','conditions'=>$automation->conditions->pluck('configuration')->all()];if(!$this->conditions->evaluate($group,$data))return $this->finish($execution,'skipped',[]);$results=[];$failed=false;foreach($automation->actions as $action){$result=$this->actions->execute($action,$execution);$results[]=$result;if(!$result['success']){$failed=true;if(!$action->continue_on_failure)break;}}$automation->update(['last_executed_at'=>now()]);return $this->finish($execution,$failed?'failed':'completed',$results,$failed?'One or more actions failed.':null);}catch(\Throwable $e){return $this->finish($execution,'failed',[],$e->getMessage());}
 }
 private function finish(AutomationExecution $e,string $status,array $results=[],?string $error=null):AutomationExecution{$e->update(['status'=>$status,'completed_at'=>now(),'error_message'=>$error,'result_summary'=>['actions'=>$results]]);return $e->refresh()->load('logs');}
 private function redact(array $data):array{foreach($data as $k=>$v){if(preg_match('/token|password|secret|key/i',(string)$k))$data[$k]='[REDACTED]';elseif(is_array($v))$data[$k]=$this->redact($v);}return $data;}
}
