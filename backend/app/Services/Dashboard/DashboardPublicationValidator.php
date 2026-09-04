<?php
namespace App\Services\Dashboard;
use App\Models\{Dashboard,Device,ResourceRevision,User};use Illuminate\Validation\ValidationException;
final class DashboardPublicationValidator
{
 public function __construct(private DashboardWidgetRegistry $widgets){}
 public function validate(Dashboard $dashboard,ResourceRevision $revision,User $actor):void
 {
  if($dashboard->scope_type!=='personal'||!$dashboard->organization_id)throw ValidationException::withMessages(['dashboard'=>['Only personal Organization Dashboards may be published.']]);
  if($revision->resource_type!=='dashboard'||(int)$revision->resource_id!==(int)$dashboard->id)throw ValidationException::withMessages(['revision_id'=>['Revision must belong to this Dashboard.']]);
  $snapshot=$revision->snapshot;$items=$snapshot['dashboard']['widgets']??null;if(!is_array($items)||count($items)>30)throw ValidationException::withMessages(['revision'=>['Dashboard revision has an invalid widget collection.']]);
  foreach($items as $widget){if(!is_array($widget)||!$this->widgets->isRegistered((string)($widget['type']??'')))throw ValidationException::withMessages(['revision'=>['Dashboard contains an unsupported widget type.']]);$configuration=$widget['configuration']??[];if(isset($configuration['deviceId'])){$device=Device::find($configuration['deviceId']);if(!$device||(int)$device->organization_id!==(int)$dashboard->organization_id)throw ValidationException::withMessages(['revision'=>['Dashboard contains an unavailable or foreign source.']]);}$this->widgets->validate($actor,'personal',['id'=>$widget['id']??null,'type'=>$widget['type']??null,'title'=>$widget['title']??null,'layout'=>$widget['layout']??null,'configuration'=>$configuration]);}
 }
}