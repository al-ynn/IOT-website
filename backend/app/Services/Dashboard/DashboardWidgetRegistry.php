<?php

namespace App\Services\Dashboard;

use App\Models\{Device, User};
use App\Services\Admin\DeviceAccessService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\{Rule, ValidationException};

final class DashboardWidgetRegistry
{
    public const DATE_RANGES=['1h','6h','24h','7d','30d'];
    public const CONTEXTS=['personal','device','admin_global'];
    private const NO_DEVICE=['label','event_count_tile','latest_events','event_count_chart','events_over_time','events_breakdown_over_time','events_by_organization','events_by_template','activations'];
    private const TELEMETRY=['switch','slider','metrics_over_time','metric_by_devices','metric','gauge','chart','table'];
    private const MAPS=['geo_map','image_map','device_connection_map'];
    private const FLEET=['device_count','device_table','metric_by_devices','events_by_device','geo_map','device_connection_map','metrics_over_time'];

    private const DEFINITIONS=[
        'switch'=>['name'=>'Switch','description'=>'Read-only boolean Device parameter state; writes remain unavailable without a supported transport.','category'=>'Controls','contexts'=>['personal','device'],'contract'=>'readonly_boolean_parameter','default'=>['title'=>'Switch'],'layout'=>['w'=>3,'h'=>3,'minW'=>2,'minH'=>3,'maxW'=>6,'maxH'=>6]],
        'slider'=>['name'=>'Slider','description'=>'Read-only numeric Device parameter state with configured bounds.','category'=>'Controls','contexts'=>['personal','device'],'contract'=>'readonly_numeric_parameter','default'=>['title'=>'Slider','minimum'=>0,'maximum'=>100],'layout'=>['w'=>3,'h'=>3,'minW'=>2,'minH'=>3,'maxW'=>6,'maxH'=>6]],
        'label'=>['name'=>'Label','description'=>'Safe static or authorized Device value label.','category'=>'Tiles','contexts'=>['personal','device'],'contract'=>'safe_label','default'=>['title'=>'Label'],'layout'=>['w'=>3,'h'=>3,'minW'=>2,'minH'=>2,'maxW'=>6,'maxH'=>6]],
        'device_count'=>['name'=>'Device count','description'=>'Count of Devices currently accessible to the viewer.','category'=>'Tiles','contexts'=>['personal','device'],'contract'=>'authorized_device_count','default'=>['title'=>'Device count'],'layout'=>['w'=>3,'h'=>2,'minW'=>2,'minH'=>2,'maxW'=>6,'maxH'=>6]],
        'device_table'=>['name'=>'Device table','description'=>'Bounded table of authorized Devices and live values.','category'=>'Tables','contexts'=>['personal','device'],'contract'=>'authorized_device_table','default'=>['title'=>'Device table'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'geo_map'=>['name'=>'Geomap','description'=>'Authorized locations with configured geographic coordinates.','category'=>'Maps','contexts'=>['personal','device'],'contract'=>'authorized_geo_markers','default'=>['title'=>'Geomap'],'layout'=>['w'=>6,'h'=>5,'minW'=>4,'minH'=>4,'maxW'=>12,'maxH'=>12]],
        'image_map'=>['name'=>'Image map','description'=>'Protected floorplan with relative-position authorized markers.','category'=>'Maps','contexts'=>['personal','device'],'contract'=>'protected_image_markers','default'=>['title'=>'Image map'],'layout'=>['w'=>6,'h'=>5,'minW'=>4,'minH'=>4,'maxW'=>12,'maxH'=>12]],
        'device_connection_map'=>['name'=>'Device connection statuses map','description'=>'Authorized Device online/offline status visualization.','category'=>'Maps','contexts'=>['personal','device'],'contract'=>'authorized_device_status_markers','default'=>['title'=>'Device connection statuses map'],'layout'=>['w'=>6,'h'=>5,'minW'=>4,'minH'=>4,'maxW'=>12,'maxH'=>12]],
        'metrics_over_time'=>['name'=>'Metrics over time, aggregated','description'=>'Bounded aggregated telemetry time series.','category'=>'Charts','contexts'=>['personal','device'],'contract'=>'time_series_numeric','default'=>['title'=>'Metrics over time, aggregated','timeRange'=>'24h','chartType'=>'line'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'metric_by_devices'=>['name'=>'Metric by devices','description'=>'Bounded metric comparison across authorized Devices.','category'=>'Charts','contexts'=>['personal','device'],'contract'=>'authorized_device_series','default'=>['title'=>'Metric by devices','timeRange'=>'24h','chartType'=>'line'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'event_count_tile'=>['name'=>'Event count','description'=>'Current authorized operational Event count.','category'=>'Events','contexts'=>['personal','device'],'contract'=>'authorized_event_count','default'=>['title'=>'Event count'],'layout'=>['w'=>3,'h'=>3,'minW'=>2,'minH'=>3,'maxW'=>6,'maxH'=>6]],
        'latest_events'=>['name'=>'Latest events','description'=>'Bounded latest authorized operational Events.','category'=>'Events','contexts'=>['personal','device'],'contract'=>'authorized_event_list','default'=>['title'=>'Latest events'],'layout'=>['w'=>4,'h'=>4,'minW'=>3,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'event_count_chart'=>['name'=>'Event count chart','description'=>'Authorized Event counts by category.','category'=>'Events','contexts'=>['personal','device'],'contract'=>'authorized_event_categories','default'=>['title'=>'Event count chart'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'events_over_time'=>['name'=>'All events over time','description'=>'Bounded chronological Event counts.','category'=>'Events','contexts'=>['personal','device'],'contract'=>'authorized_event_time_series','default'=>['title'=>'All events over time'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'events_breakdown_over_time'=>['name'=>'Events breakdown over time','description'=>'Bounded Event category series.','category'=>'Events','contexts'=>['personal','device'],'contract'=>'authorized_event_breakdown','default'=>['title'=>'Events breakdown over time'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'events_by_organization'=>['name'=>'Events by organization','description'=>'Current-Organization Event grouping only.','category'=>'Events','contexts'=>['personal','device'],'contract'=>'current_organization_events','default'=>['title'=>'Events by organization'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'events_by_device'=>['name'=>'Events by devices','description'=>'Event counts for authorized Devices only.','category'=>'Events','contexts'=>['personal','device'],'contract'=>'authorized_device_event_counts','default'=>['title'=>'Events by devices'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'events_by_template'=>['name'=>'Events by templates','description'=>'Event counts grouped by authorized Device Templates.','category'=>'Events','contexts'=>['personal','device'],'contract'=>'authorized_template_event_counts','default'=>['title'=>'Events by templates'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'activations'=>['name'=>'Activations','description'=>'Current-Organization completed provisioning sessions over time.','category'=>'Organization data','contexts'=>['personal'],'contract'=>'authorized_provisioning_activations','default'=>['title'=>'Activations'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'metric'=>['name'=>'Latest Value','description'=>'Latest real telemetry value.','category'=>'Telemetry','contexts'=>['personal','device'],'contract'=>'single_value','default'=>['title'=>'Latest Value','timeRange'=>'24h'],'layout'=>['w'=>3,'h'=>3,'minW'=>2,'minH'=>2,'maxW'=>12,'maxH'=>12]],
        'gauge'=>['name'=>'Gauge','description'=>'Numeric telemetry value within explicit display bounds.','category'=>'Telemetry','contexts'=>['personal','device'],'contract'=>'single_numeric_value','default'=>['title'=>'Gauge','timeRange'=>'24h','minimum'=>0,'maximum'=>100],'layout'=>['w'=>3,'h'=>3,'minW'=>3,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'chart'=>['name'=>'Time Series','description'=>'Bounded real numeric telemetry history.','category'=>'Telemetry','contexts'=>['personal','device'],'contract'=>'time_series_numeric','default'=>['title'=>'Time Series','timeRange'=>'24h','chartType'=>'line'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'table'=>['name'=>'Telemetry Table','description'=>'Bounded recent telemetry records.','category'=>'Telemetry','contexts'=>['personal','device'],'contract'=>'telemetry_record_list','default'=>['title'=>'Telemetry Table','timeRange'=>'24h'],'layout'=>['w'=>6,'h'=>4,'minW'=>4,'minH'=>3,'maxW'=>12,'maxH'=>12]],
        'status'=>['name'=>'Device Status','description'=>'Factual canonical Device status.','category'=>'Device','contexts'=>['personal','device'],'contract'=>'device_status','default'=>['title'=>'Device Status'],'layout'=>['w'=>3,'h'=>3,'minW'=>2,'minH'=>2,'maxW'=>12,'maxH'=>12]],
        'device'=>['name'=>'Device Summary','description'=>'Authorized Device identity and last activity.','category'=>'Device','contexts'=>['personal','device'],'contract'=>'device_summary','default'=>['title'=>'Device Summary'],'layout'=>['w'=>3,'h'=>3,'minW'=>3,'minH'=>2,'maxW'=>12,'maxH'=>12]],
        'global_device_summary'=>['name'=>'Global Device Summary','description'=>'Admin-authorized platform Device counts.','category'=>'Operations','contexts'=>['admin_global'],'contract'=>'admin_global_device_summary','default'=>['title'=>'Global Device Summary'],'layout'=>['w'=>6,'h'=>3,'minW'=>4,'minH'=>2,'maxW'=>12,'maxH'=>12]],
        'global_failure_summary'=>['name'=>'Failures — Last 24 Hours','description'=>'Admin-authorized persisted failure totals.','category'=>'Operations','contexts'=>['admin_global'],'contract'=>'admin_global_failure_summary','default'=>['title'=>'Failures — Last 24 Hours'],'layout'=>['w'=>6,'h'=>3,'minW'=>4,'minH'=>2,'maxW'=>12,'maxH'=>12]],
    ];

    public function __construct(private DeviceAccessService $deviceAccess) {}

    public function definitions(string $scope): array
    {
        $this->assertContext($scope);
        $definitions=[];
        foreach(self::DEFINITIONS as $key=>$definition){
            if(!in_array($scope,$definition['contexts'],true))continue;
            $definitions[]=['type'=>$key,'label'=>$definition['name'],'description'=>$definition['description'],'category'=>$definition['category'],'contexts'=>$definition['contexts'],'scope'=>$scope,'dataContract'=>$definition['contract'],'configSchemaVersion'=>1,'defaultSettings'=>$definition['default'],'defaultLayout'=>['x'=>0,'y'=>0,'w'=>$definition['layout']['w'],'h'=>$definition['layout']['h']],'minWidth'=>$definition['layout']['minW'],'minHeight'=>$definition['layout']['minH'],'maxWidth'=>$definition['layout']['maxW'],'maxHeight'=>$definition['layout']['maxH'],'resizable'=>true,'removable'=>true,'duplicable'=>false,'requiresDevice'=>!in_array($key,self::NO_DEVICE,true)&&!str_starts_with($key,'global_'),'requiresExistingSourceConfiguration'=>in_array($key,self::TELEMETRY,true),'dateRanges'=>in_array($key,self::TELEMETRY,true)?self::DATE_RANGES:[]];
        }
        return $definitions;
    }

    public function validate(User $user,string $scope,array $widget,?Device $contextDevice=null,?array $authorizedDeviceIds=null):array
    {
        $this->assertContext($scope);$allowed=array_keys(array_filter(self::DEFINITIONS,fn($d)=>in_array($scope,$d['contexts'],true)));
        $base=Validator::make($widget,['id'=>['required','uuid'],'type'=>['required',Rule::in($allowed)],'title'=>['nullable','string','max:120'],'layout'=>['required','array:x,y,w,h'],'layout.x'=>['required','integer','min:0','max:11'],'layout.y'=>['required','integer','min:0','max:1000'],'layout.w'=>['required','integer','min:1','max:12'],'layout.h'=>['required','integer','min:1','max:12'],'configuration'=>['present','array']])->validate();
        $definition=self::DEFINITIONS[$base['type']];$limits=$definition['layout'];$layout=$base['layout'];
        if($layout['w']<$limits['minW']||$layout['h']<$limits['minH']||$layout['w']>$limits['maxW']||$layout['h']>$limits['maxH']||$layout['x']+$layout['w']>12)throw ValidationException::withMessages(['layout'=>['Widget dimensions are outside the registered bounds.']]);
        $type=$base['type'];$configuration=$widget['configuration'];
        if(str_starts_with($type,'global_')||in_array($type,self::NO_DEVICE,true)){
            $rules=$type==='label'?['staticValue'=>['nullable','string','max:500'],'unit'=>['nullable','string','max:20']]:[];
            if(array_diff(array_keys($configuration),array_keys($rules)))throw ValidationException::withMessages(['configuration'=>['Unsupported widget configuration field.']]);
            $configuration=$rules?Validator::make($configuration,$rules)->validate():[];
        }
        else{
            if($type==='image_map'){
                $rules=['deviceId'=>['nullable','integer'],'imageAssetId'=>['nullable','integer'],'markers'=>['nullable','array','max:100']];
                if(array_diff(array_keys($configuration),array_keys($rules)))throw ValidationException::withMessages(['configuration'=>['Unsupported widget configuration field.']]);
                $configuration=Validator::make($configuration,$rules)->validate();
                foreach($configuration['markers']??[] as $index=>$marker){
                    Validator::make($marker,['id'=>['required','string','max:80'],'x'=>['required','numeric','between:0,1'],'y'=>['required','numeric','between:0,1'],'deviceId'=>['nullable','integer'],'label'=>['nullable','string','max:120']])->validate();
                    if(isset($marker['deviceId'])) {
                        $allowed = $authorizedDeviceIds !== null ? in_array((string)$marker['deviceId'], $authorizedDeviceIds, true) : ($this->deviceAccess->canViewDevice($user, Device::findOrFail($marker['deviceId'])));
                        if(!$allowed) throw ValidationException::withMessages(["configuration.markers.{$index}.deviceId"=>['The selected Device is not accessible.']]);
                    }
                }
                if (isset($configuration['deviceId'])) {
                    $allowed = $authorizedDeviceIds !== null ? in_array((string)$configuration['deviceId'], $authorizedDeviceIds, true) : ($this->deviceAccess->canViewDevice($user, Device::findOrFail($configuration['deviceId'])));
                    if (!$allowed) throw ValidationException::withMessages(['configuration.deviceId'=>['The selected Device is not accessible.']]);
                }
                return ['id'=>$base['id'],'type'=>$type,'title'=>trim((string)($base['title']??$definition['name'])),'layout'=>$layout,'configuration'=>$configuration];
            }
            $rules=['deviceId'=>[in_array($type,self::FLEET,true)?'nullable':'required','integer'],'sourceMode'=>['nullable',Rule::in(['explicit_devices','template_devices','authorized_device_set'])],'deviceIds'=>['nullable','array','max:100'],'deviceTemplateId'=>['nullable','integer']];
            if(in_array($type,self::TELEMETRY,true)){$rules['telemetryKey']=['required','string','max:100','regex:/^[A-Za-z0-9_.:-]+$/'];$rules['unit']=['nullable','string','max:20'];$rules['timeRange']=['nullable',Rule::in(self::DATE_RANGES)];}
            if(in_array($type,['chart','metrics_over_time','metric_by_devices'],true))$rules['chartType']=['nullable',Rule::in(['line','area','bar'])];
            if(in_array($type,['gauge','slider'],true)){$rules['minimum']=['required','numeric','between:-1000000000,1000000000'];$rules['maximum']=['required','numeric','gt:minimum','between:-1000000000,1000000000'];}
            if(array_diff(array_keys($configuration),array_keys($rules)))throw ValidationException::withMessages(['configuration'=>['Unsupported widget configuration field.']]);
            $configuration=Validator::make($configuration,$rules)->validate();
            $ids=array_values(array_unique(array_map('strval',$configuration['deviceIds']??[])));
            if(isset($configuration['deviceId']))$ids[]=(string)$configuration['deviceId'];
            if($type==='device' || $type==='status' || $type==='switch' || $type==='slider')$ids=array_values(array_unique($ids));
            foreach($ids as $id){
                $allowedDevice=$authorizedDeviceIds!==null ? in_array($id,$authorizedDeviceIds,true) : ($this->deviceAccess->canViewDevice($user,Device::findOrFail($id)));
                if(!$allowedDevice)throw ValidationException::withMessages(['configuration.deviceIds'=>['One or more selected Devices are not accessible.']]);
            }
            if(!$ids && !in_array($type,self::FLEET,true))throw ValidationException::withMessages(['configuration.deviceId'=>['The selected Device is required.']]);
        }
        return ['id'=>$base['id'],'type'=>$type,'title'=>trim((string)($base['title']??$definition['name'])),'layout'=>$layout,'configuration'=>$configuration];
    }

    public function isRegistered(string $type):bool{return isset(self::DEFINITIONS[$type]);}
    private function assertContext(string $scope):void{if(!in_array($scope,self::CONTEXTS,true))throw ValidationException::withMessages(['context'=>['Unsupported Dashboard context.']]);}
}
