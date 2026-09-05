<?php

namespace Database\Seeders;

use App\Models\{Dashboard,DashboardWidget,Device,DeviceAccessAssignment,DeviceTemplate,Notification,Organization,ResourceCollaborator,ResourceEditingSession,ResourcePublicationSubmission,ResourceRevision,ResourceRevisionState,User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;

final class BrowserQaSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403);

        DB::transaction(function (): void {
            $orgA=Organization::updateOrCreate(['slug'=>'phase103-org-a'],['name'=>'Phase103 Organization A','status'=>'active']);
            $orgB=Organization::updateOrCreate(['slug'=>'phase103-org-b'],['name'=>'Phase103 Organization B','status'=>'active']);
            $user=function(string$email,string$name,Organization$org,bool$admin=false):User{return User::updateOrCreate(['email'=>$email],['name'=>$name,'password'=>Hash::make('Browser123!'),'organization_id'=>$org->id,'role'=>'staff','platform_role'=>$admin?'platform_admin':null,'status'=>'active']);};
            $admin1=$user('admin1@phase103.test','Phase103 Admin One',$orgA,true);
            $admin2=$user('admin2@phase103.test','Phase103 Admin Two',$orgA,true);
            $viewer=$user('viewer@phase103.test','Phase103 Device Viewer',$orgA);
            $full=$user('full@phase103.test','Phase103 Device Full Access',$orgA);
            $unassigned=$user('unassigned@phase103.test','Phase103 Unassigned Staff',$orgA);
            $editor2=$user('editor2@phase103.test','Phase103 Second Editor',$orgA);
            $recipient=$user('recipient@phase103.test','Phase103 Share Recipient',$orgA);
            $orgBStaff=$user('staff@phase103-org-b.test','Phase103 Org B Staff',$orgB);

            $d1=Device::updateOrCreate(['external_id'=>'PHASE103-D1'],['organization_id'=>$orgA->id,'created_by'=>$admin1->id,'name'=>'Phase103 Shared Temperature Device','type'=>'sensor','protocol'=>'mqtt','status'=>'online']);
            $d2=Device::updateOrCreate(['external_id'=>'PHASE103-D2'],['organization_id'=>$orgA->id,'created_by'=>$admin1->id,'name'=>'Phase103 Restricted Pressure Device','type'=>'sensor','protocol'=>'mqtt','status'=>'online']);
            Device::updateOrCreate(['external_id'=>'PHASE103-ORGB'],['organization_id'=>$orgB->id,'created_by'=>$orgBStaff->id,'name'=>'Phase103 Foreign Device','type'=>'sensor','protocol'=>'mqtt','status'=>'online']);
            DeviceAccessAssignment::updateOrCreate(['device_id'=>$d1->id,'user_id'=>$viewer->id],['access_level'=>'viewer','assigned_by'=>$admin1->id]);
            DeviceAccessAssignment::updateOrCreate(['device_id'=>$d1->id,'user_id'=>$full->id],['access_level'=>'full_access','assigned_by'=>$admin1->id]);
            DeviceAccessAssignment::updateOrCreate(['device_id'=>$d1->id,'user_id'=>$editor2->id],['access_level'=>'full_access','assigned_by'=>$admin1->id]);

            $deviceDashboard=Dashboard::updateOrCreate(['device_id'=>$d1->id],['organization_id'=>$orgA->id,'owner_user_id'=>$admin1->id,'created_by'=>$admin1->id,'updated_by'=>$admin1->id,'name'=>'Phase103 Accepted Dashboard','scope_type'=>'device','is_default'=>true,'layout_version'=>1]);
            $deviceWidget=DashboardWidget::updateOrCreate(['dashboard_id'=>$deviceDashboard->id,'position'=>0],['id'=>(string)Str::uuid(),'widget_type'=>'status','title'=>'Phase103 Device Status','layout'=>['x'=>0,'y'=>0,'w'=>3,'h'=>3],'configuration'=>[],'position'=>0]);
            $snapshot=function(string$name)use($deviceWidget):array{return ['metadata'=>[],'dashboard'=>['name'=>$name,'description'=>null,'widgets'=>[['id'=>$deviceWidget->id,'type'=>'status','title'=>$deviceWidget->title,'layout'=>$deviceWidget->layout,'configuration'=>$deviceWidget->configuration]]],'parameters'=>[]];};
            $deviceR1=ResourceRevision::firstOrCreate(['resource_type'=>'device','resource_id'=>$d1->id,'revision_number'=>1],['created_by'=>$admin1->id,'change_summary'=>'Accepted browser baseline','snapshot_schema_version'=>1,'snapshot'=>$snapshot('Phase103 Accepted Dashboard'),'changed_sections'=>['dashboard'],'checksum'=>hash('sha256','phase103-device-r1'),'created_at'=>now()->subMinute()]);
            $deviceR2=ResourceRevision::firstOrCreate(['resource_type'=>'device','resource_id'=>$d1->id,'revision_number'=>2],['parent_revision_id'=>$deviceR1->id,'created_by'=>$full->id,'change_summary'=>'Incoming browser update','snapshot_schema_version'=>1,'snapshot'=>$snapshot('Phase103 Latest Dashboard'),'changed_sections'=>['dashboard'],'checksum'=>hash('sha256','phase103-device-r2'),'created_at'=>now()]);
            foreach([[$viewer,$deviceR1],[$full,$deviceR2],[$editor2,$deviceR2]] as [$stateUser,$accepted]) ResourceRevisionState::updateOrCreate(['resource_type'=>'device','resource_id'=>$d1->id,'user_id'=>$stateUser->id],['accepted_revision_id'=>$accepted->id,'seen_latest_revision_id'=>$accepted->id,'last_reviewed_revision_id'=>$accepted->id]);
            ResourceEditingSession::create(['user_id'=>$full->id,'resource_type'=>'device','resource_id'=>(string)$d1->id,'started_at'=>now(),'last_heartbeat_at'=>now(),'expires_at'=>now()->addHour()]);

            $template=DeviceTemplate::updateOrCreate(['organization_id'=>$orgA->id,'name'=>'Phase103 Shared Template'],['created_by'=>$admin1->id,'description'=>'Safe browser fixture']);
            ResourceCollaborator::updateOrCreate(['resource_type'=>'device_template','resource_id'=>$template->id,'user_id'=>$viewer->id],['permission'=>'view','granted_by'=>$admin1->id]);
            $templateRevision=ResourceRevision::firstOrCreate(['resource_type'=>'device_template','resource_id'=>$template->id,'revision_number'=>1],['created_by'=>$admin1->id,'change_summary'=>'Browser fixture baseline','snapshot_schema_version'=>1,'snapshot'=>['metadata'=>['name'=>$template->name,'description'=>'Safe browser fixture']],'changed_sections'=>['metadata'],'checksum'=>hash('sha256','phase103-template-r1'),'created_at'=>now()]);
            ResourcePublicationSubmission::updateOrCreate(['active_key'=>"device_template:{$template->id}"],['resource_type'=>'device_template','resource_id'=>$template->id,'submitted_revision_id'=>$templateRevision->id,'submitted_by'=>$viewer->id,'status'=>'submitted','submitted_at'=>now()]);

            $dashboard=Dashboard::updateOrCreate(['organization_id'=>$orgA->id,'name'=>'Phase103 Shared Source Boundary'],['owner_user_id'=>$admin1->id,'created_by'=>$admin1->id,'updated_by'=>$admin1->id,'scope_type'=>'personal','is_default'=>false,'layout_version'=>1]);
            ResourceCollaborator::updateOrCreate(['resource_type'=>'dashboard','resource_id'=>$dashboard->id,'user_id'=>$viewer->id],['permission'=>'view','granted_by'=>$admin1->id]);
            DashboardWidget::updateOrCreate(['id'=>(string)Str::uuid()],['dashboard_id'=>$dashboard->id,'widget_type'=>'metric','title'=>'Restricted pressure','layout'=>['x'=>0,'y'=>0,'w'=>3,'h'=>3],'configuration'=>['deviceId'=>$d2->id,'telemetryKey'=>'pressure'],'position'=>0]);

            Notification::updateOrCreate(['deduplication_key'=>'phase103-viewer-notice'],['organization_id'=>$orgA->id,'user_id'=>$viewer->id,'actor_id'=>$admin1->id,'type'=>'system.notice','category'=>'system','title'=>'Phase103 unread notice','message'=>'Safe browser fixture notification','severity'=>'info']);

            foreach([10301=>[$viewer,'phase103-viewer-token'],10302=>[$unassigned,'phase103-unassigned-token'],10303=>[$admin1,'phase103-admin1-token'],10304=>[$admin2,'phase103-admin2-token'],10305=>[$full,'phase103-full-token'],10306=>[$editor2,'phase103-editor2-token'],10307=>[$recipient,'phase103-recipient-token']]as$id=>[$tokenUser,$plain])DB::table('personal_access_tokens')->updateOrInsert(['id'=>$id],['tokenable_type'=>User::class,'tokenable_id'=>$tokenUser->id,'name'=>'phase103-browser','token'=>hash('sha256',$plain),'abilities'=>json_encode(['*']),'created_at'=>now(),'updated_at'=>now()]);

        });
    }
}
