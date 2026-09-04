<?php
namespace App\Notifications;
use App\Models\{Notification,User};
use App\Collaboration\{CollaborationResourceReference,CollaborationResourceRegistry};
use App\Services\Admin\DeviceAccessService;
final class NotificationDeepLinkResolver{
public function resolve(Notification $n,?User $viewer=null):?string{
if($n->type==='device.created'&&$n->resource_type==='device'&&$n->resource_id)return $viewer?->isPlatformAdmin()?'/admin/devices/'.(int)$n->resource_id:null;
if(in_array($n->type,['comment.created','comment.mentioned','comment.acknowledged'],true)&&$n->resource_type==='device'&&$n->resource_id&&isset($n->data['thread_id'])){if(!$viewer)return null;try{app(DeviceAccessService::class)->findViewableDeviceOrFail($viewer,$n->resource_id);}catch(\Throwable){return null;}return ($n->data['anchor_type']??null)==='dashboard_widget'?'/app/devices/'.(int)$n->resource_id.'?tab=dashboard&thread='.(int)$n->data['thread_id'].'&widget='.rawurlencode((string)($n->data['anchor_key']??'')):'/app/devices/'.(int)$n->resource_id.'?tab=comments&thread='.(int)$n->data['thread_id'];}
if(in_array($n->type,['comment.created','comment.mentioned','comment.acknowledged'],true)&&$n->resource_type==='dashboard'&&$n->resource_id&&isset($n->data['thread_id'])){if(!$viewer)return null;try{app(CollaborationResourceRegistry::class)->resolve($viewer,new CollaborationResourceReference('dashboard',$n->resource_id),'view');}catch(\Throwable){return null;}return '/app/dashboard/'.(int)$n->resource_id.'?thread='.(int)$n->data['thread_id'].(($n->data['anchor_type']??null)==='dashboard_widget'?'&widget='.rawurlencode((string)($n->data['anchor_key']??'')):'');}
if($n->type==='revision.available'&&$n->resource_type==='device'&&$n->resource_id){if(!$viewer)return null;try{app(DeviceAccessService::class)->findViewableDeviceOrFail($viewer,$n->resource_id);}catch(\Throwable){return null;}return '/app/devices/'.(int)$n->resource_id.'?tab=dashboard';}
$path=$n->action_url;if(!is_string($path)||!preg_match('#^/(app|admin)/[A-Za-z0-9/_?=&-]+$#',$path))return null;if(str_starts_with($path,'/admin/')&&!$viewer?->isPlatformAdmin())return null;return $path;
}}