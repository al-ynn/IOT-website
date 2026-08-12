<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
 public function run(): void
 {
  $free=Plan::updateOrCreate(['id'=>'free'],['name'=>'Free','description'=>'Core IoT monitoring','price'=>0,'currency'=>'USD','interval'=>'monthly','features'=>['dashboard.basic','dashboard.customize','telemetry.basic','reports.basic','devices.management'],'device_limit'=>5,'user_limit'=>2,'dashboard_limit'=>2,'automation_limit'=>0,'active'=>true,'is_default'=>true]);
  Plan::updateOrCreate(['id'=>'pro-monthly'],['name'=>'Pro','description'=>'Advanced IoT operations','price'=>49,'currency'=>'USD','interval'=>'monthly','features'=>['dashboard.basic','dashboard.customize','telemetry.basic','devices.management','automation.basic','automation.advanced','analytics.advanced','reports.export','reports.scheduled','theme.premium'],'device_limit'=>50,'user_limit'=>10,'dashboard_limit'=>20,'automation_limit'=>20,'active'=>true]);
  Plan::updateOrCreate(['id'=>'pro-yearly'],['name'=>'Pro Yearly','description'=>'Advanced IoT operations billed yearly','price'=>490,'currency'=>'USD','interval'=>'yearly','features'=>['dashboard.basic','dashboard.customize','telemetry.basic','devices.management','automation.basic','automation.advanced','analytics.advanced','reports.export','reports.scheduled','theme.premium'],'device_limit'=>50,'user_limit'=>10,'dashboard_limit'=>20,'automation_limit'=>20,'active'=>true]);
  Plan::updateOrCreate(['id'=>'lifetime'],['name'=>'Lifetime','description'=>'One-time lifetime access','price'=>999,'currency'=>'USD','interval'=>'lifetime','features'=>['dashboard.basic','dashboard.customize','telemetry.basic','devices.management','automation.basic','automation.advanced','analytics.advanced','reports.export','reports.scheduled','theme.premium'],'device_limit'=>100,'user_limit'=>25,'dashboard_limit'=>50,'automation_limit'=>50,'active'=>true]);
  \App\Models\Organization::doesntHave('subscriptions')->each(fn($organization)=>$organization->subscriptions()->create(['plan_id'=>$free->id,'status'=>'active','current_period_start'=>now()]));
  if (\Illuminate\Support\Facades\Schema::hasTable('automation_templates')) {
   \App\Models\AutomationTemplate::updateOrCreate(['organization_id'=>null,'name'=>'Telemetry Alert'],['description'=>'Create an in-app alert when a telemetry value crosses a threshold.','category'=>'Monitoring','active'=>true,'definition'=>['name'=>'Telemetry Alert','description'=>'Notify when telemetry crosses a threshold','enabled'=>false,'trigger'=>['type'=>'telemetry','field'=>'temperature'],'conditions'=>['logic'=>'AND','conditions'=>[['field'=>'temperature','operator'=>'>','value'=>80]]],'actions'=>[['type'=>'notification','target'=>'organization','payload'=>['message'=>'Telemetry threshold exceeded']]]]]);
  }
 }
}
