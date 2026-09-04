<?php
namespace App\Http\Controllers;
use App\Services\{ReminderSchedulingService,RevisionReminderService};use Illuminate\Http\Request;use Illuminate\Validation\Rule;
final class RevisionReminderController extends Controller{
 public function __construct(private ReminderSchedulingService$reminders){}
 public function show(Request$r,string$type,string$resource){return$this->reminders->status($r->user(),'resource_update',$type,$resource);}
 public function store(Request$r,string$type,string$resource){$data=$r->validate(['preset'=>['required',Rule::in(RevisionReminderService::PRESETS)],'user_id'=>['prohibited'],'remind_at'=>['prohibited'],'status'=>['prohibited'],'generation'=>['prohibited']]);return$this->reminders->schedule($r->user(),'resource_update',$type,$resource,$data['preset']);}
 public function destroy(Request$r,string$type,string$resource){return$this->reminders->cancel($r->user(),'resource_update',$type,$resource);}
}