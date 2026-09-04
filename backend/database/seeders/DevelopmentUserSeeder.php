<?php

namespace Database\Seeders;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceParameter;
use App\Models\DeviceTemplate;
use App\Models\DeviceTemplateMetadataDefinition;
use App\Models\DeviceTemplateEventDefinition;
use App\Models\DeviceMetadataValue;
use App\Models\DeviceEventFact;
use App\Models\Location;
use App\Models\OperationalEvent;
use App\Models\Organization;
use App\Models\ProvisioningSession;
use App\Models\TelemetryRecord;
use App\Models\User;
use App\Services\Dashboard\DashboardWidgetRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\DashboardMapAsset;

class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) return;

        DB::transaction(function (): void {
            $organization = Organization::updateOrCreate(
                ['slug' => 'sample-iot-demo'],
                ['name' => 'Sample IoT Demo', 'status' => 'active']
            );
            Organization::where('slug', 'iot-platform-demo')->whereKeyNot($organization->id)->delete();

            $staff = User::updateOrCreate(['email' => 'staff@iot-platform.test'], [
                'name' => 'Sample IoT Staff', 'password' => Hash::make('Password123!'),
                'organization_id' => $organization->id, 'role' => 'staff', 'platform_role' => null, 'status' => 'active',
            ]);
            $admin = User::updateOrCreate(['email' => 'admin@iot-platform.test'], [
                'name' => 'Sample IoT Admin', 'password' => Hash::make('Admin123!'),
                'organization_id' => $organization->id, 'role' => 'staff', 'platform_role' => 'platform_admin', 'status' => 'active',
            ]);

            Device::where('organization_id', $organization->id)->where('external_id', '!=', 'SAMPLE-ENV-CONTROLLER')->delete();
            DeviceTemplate::where('organization_id', $organization->id)->where('name', '!=', 'Sample Environmental Controller')->delete();
            Dashboard::where('organization_id', $organization->id)->where('name', '!=', 'Sample Device Dashboard')->delete();
            Location::where('organization_id', $organization->id)->where('name', '!=', 'Sample Facility')->delete();

            $location = Location::updateOrCreate(
                ['organization_id' => $organization->id, 'normalized_name' => 'sample facility'],
                ['name' => 'Sample Facility', 'description' => 'SAMPLE DATA — fictional demonstration facility.', 'latitude' => 14.5995, 'longitude' => 120.9842, 'created_by' => $admin->id]
            );
            $template = DeviceTemplate::updateOrCreate(
                ['organization_id' => $organization->id, 'name' => 'Sample Environmental Controller'],
                ['description' => 'SAMPLE DATA — Complete demonstration Template used to preview the IoT platform.', 'device_type' => 'esp32', 'protocol' => 'mqtt', 'created_by' => $admin->id]
            );

            $definitions = [
                ['Temperature', 'temperature', 'number', '°C', 'temperature', ['min' => -40, 'max' => 125, 'precision' => 1]],
                ['Humidity', 'humidity', 'number', '%', 'humidity', ['min' => 0, 'max' => 100, 'precision' => 1]],
                ['Pressure', 'pressure', 'number', 'hPa', 'pressure', ['min' => 900, 'max' => 1100, 'precision' => 1]],
                ['Battery Voltage', 'battery_voltage', 'number', 'V', 'voltage', ['min' => 3, 'max' => 4.2, 'precision' => 2]],
                ['Signal Strength', 'signal_strength', 'integer', 'dBm', 'signal', ['min' => -120, 'max' => 0]],
                ['Motion Detected', 'motion_detected', 'boolean', null, 'motion', ['trueLabel' => 'Motion', 'falseLabel' => 'Clear']],
                ['Door Open', 'door_open', 'boolean', null, 'state', ['trueLabel' => 'Open', 'falseLabel' => 'Closed']],
                ['Device Mode', 'device_mode', 'enum', null, 'state', ['options' => ['AUTO', 'MANUAL', 'STANDBY'], 'default' => 'AUTO']],
                ['Device Message', 'device_message', 'string', null, 'state', ['maxLength' => 120, 'default' => 'System operating normally']],
            ];
            $template->parameters()->delete();
            foreach ($definitions as [$name, $key, $type, $unit, $semantic, $configuration]) {
                $template->parameters()->create(compact('name', 'key', 'unit', 'semantic', 'configuration') + ['data_type' => $type, 'description' => 'SAMPLE DATA — demonstration Datastream.']);
            }

            $device = Device::updateOrCreate(['external_id' => 'SAMPLE-ENV-CONTROLLER'], [
                'organization_id' => $organization->id, 'created_by' => $admin->id, 'device_template_id' => $template->id,
                'location_id' => $location->id, 'name' => 'Sample Device — Environmental Controller', 'type' => 'sensor',
                'protocol' => 'mqtt', 'status' => 'online', 'last_seen' => now()->startOfMinute(), 'battery' => 87,
            ]);
            DeviceAccessAssignment::where('user_id', $staff->id)->where('device_id', '!=', $device->id)->delete();
            DeviceAccessAssignment::updateOrCreate(['device_id' => $device->id, 'user_id' => $staff->id], ['access_level' => 'full_access', 'assigned_by' => $admin->id]);
            $device->parameters()->delete();
            foreach ($template->parameters as $parameter) DeviceParameter::create([
                'device_id' => $device->id, 'name' => $parameter->name, 'key' => $parameter->key, 'data_type' => $parameter->data_type,
                'unit' => $parameter->unit, 'description' => $parameter->description, 'semantic' => $parameter->semantic, 'configuration' => $parameter->configuration,
            ]);
            $template->metadataDefinitions()->delete();
            $metadataDefinitions=[
                ['key'=>'serial_number','name'=>'Serial Number','data_type'=>'string','required'=>true,'configuration'=>['maxLength'=>80]],
                ['key'=>'installation_date','name'=>'Installation Date','data_type'=>'date','required'=>false,'configuration'=>[]],
                ['key'=>'hardware_revision','name'=>'Hardware Revision','data_type'=>'string','required'=>false,'configuration'=>['maxLength'=>30]],
            ];
            foreach($metadataDefinitions as $index=>$definition)$template->metadataDefinitions()->create($definition+['sort_order'=>$index,'description'=>'SAMPLE DATA — canonical Device metadata.']);
            $device->metadataValues()->delete();
            foreach($template->metadataDefinitions as $definition)$device->metadataValues()->create(['metadata_definition_id'=>$definition->id,'value'=>match($definition->key){'serial_number'=>'SAMPLE-SN-001','installation_date'=>now()->toDateString(),'hardware_revision'=>'REV-A'}]);
            $device->eventFacts()->delete();
            $template->eventDefinitions()->delete();
            $eventDefinitions=[
                ['code'=>'battery_critical','name'=>'Battery Critical','severity'=>'critical','description'=>'Battery entered critical range.'],
                ['code'=>'motion_detected','name'=>'Motion Detected','severity'=>'info','description'=>'Motion sensor detected activity.'],
                ['code'=>'temperature_warning','name'=>'Temperature Warning','severity'=>'warning','description'=>'Temperature exceeded the warning threshold.'],
            ];
            foreach($eventDefinitions as $definition)$template->eventDefinitions()->create($definition+['enabled'=>true]);

            TelemetryRecord::where('device_id', $device->id)->delete();
            $start = now()->startOfHour()->subHours(48);
            for ($index = 0; $index <= 96; $index++) {
                $at = $start->copy()->addMinutes($index * 30);
                $cycle = sin(($index % 48) / 48 * 2 * M_PI);
                $values = [
                    'temperature' => round(23.5 + 4.2 * $cycle, 1), 'humidity' => round(61 - 9 * $cycle, 1),
                    'pressure' => round(1012 + 5 * sin($index / 12), 1), 'battery_voltage' => round(4.12 - $index * .003, 2),
                    'signal_strength' => -58 - ($index % 7), 'motion_detected' => $index % 19 === 0, 'door_open' => $index % 31 === 0,
                    'device_mode' => $index % 37 === 0 ? 'MANUAL' : ($index % 53 === 0 ? 'STANDBY' : 'AUTO'),
                    'device_message' => $index % 24 === 0 ? 'Scheduled environment check complete' : 'System operating normally',
                ];
                foreach ($values as $key => $typed) {
                    $definition = $device->parameters->firstWhere('key', $key);
                    TelemetryRecord::create(['device_id' => $device->id, 'key' => $key, 'value' => is_bool($typed) ? ($typed ? 1 : 0) : (is_numeric($typed) ? $typed : 0), 'typed_value' => $typed, 'unit' => $definition?->unit, 'recorded_at' => $at]);
                }
            }

            OperationalEvent::where('device_id', $device->id)->delete();
            foreach (range(0, 23) as $index) {
                $definition=$template->eventDefinitions->get($index%$template->eventDefinitions->count());
                $device->eventFacts()->create(['event_definition_id'=>$definition->id,'event_code'=>$definition->code,'severity'=>$definition->severity,'event_name'=>$definition->name,'message'=>'SAMPLE DATA — '.$definition->description,'value'=>['sequence'=>$index],'occurred_at'=>$start->copy()->addHours($index*2)]);
            }
            foreach (range(0, 23) as $index) OperationalEvent::create([
                'organization_id' => $organization->id, 'device_id' => $device->id, 'device_bound' => true, 'source' => 'device',
                'event_type' => 'device_crash_reported', 'severity' => $index % 11 === 0 ? 'warning' : 'info',
                'title' => 'Sample Device diagnostic event',
                'message' => 'SAMPLE DATA — deterministic event using the existing Device diagnostic event domain.', 'context' => ['sample' => true, 'sequence' => $index],
                'occurred_at' => $start->copy()->addHours($index * 2),
            ]);
            ProvisioningSession::where('organization_id', $organization->id)->where('name', 'like', 'SAMPLE DATA — Activation %')->delete();
            foreach (range(1, 6) as $index) ProvisioningSession::create([
                'organization_id' => $organization->id, 'initiated_by' => $admin->id, 'device_id' => $device->id,
                'device_template_id' => $template->id, 'name' => "SAMPLE DATA — Activation {$index}", 'status' => 'completed',
                'started_at' => $start->copy()->addHours($index * 7), 'completed_at' => $start->copy()->addHours($index * 7)->addMinutes(3),
                'expires_at' => $start->copy()->addHours($index * 7)->addHour(),
            ]);

            $dashboard = Dashboard::updateOrCreate(['organization_id' => $organization->id, 'name' => 'Sample Device Dashboard'], [
                'owner_user_id' => $admin->id, 'created_by' => $admin->id, 'updated_by' => $admin->id,
                'description' => null, 'scope_type' => 'personal', 'is_default' => true, 'layout_version' => 1,
            ]);
            $assetPath = 'dashboard-map-assets/'.$dashboard->id.'/sample-floorplan.png';
            if (! Storage::disk('local')->exists($assetPath)) {
                Storage::disk('local')->put($assetPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
            }
            $mapAsset = DashboardMapAsset::updateOrCreate(['dashboard_id' => $dashboard->id, 'path' => $assetPath], ['uploaded_by' => $admin->id, 'disk' => 'local', 'mime_type' => 'image/png', 'size' => Storage::disk('local')->size($assetPath), 'width' => 1, 'height' => 1]);
            $dashboard->widgets()->delete();
            $x = 0; $y = 0; $rowHeight = 0;
            foreach (app(DashboardWidgetRegistry::class)->definitions('personal') as $position => $definition) {
                $layout = $definition['defaultLayout'];
                if ($x + $layout['w'] > 12) { $x = 0; $y += $rowHeight; $rowHeight = 0; }
                $layout['x'] = $x; $layout['y'] = $y; $x += $layout['w']; $rowHeight = max($rowHeight, $layout['h']);
                $type = $definition['type'];
                $configuration = [];
                if ($definition['requiresDevice']) {
                    $key = $type === 'switch' ? 'motion_detected' : ($type === 'slider' ? 'humidity' : 'temperature');
                    $configuration = ['deviceId' => $device->id];
                    if ($definition['requiresExistingSourceConfiguration']) $configuration += ['telemetryKey' => $key, 'unit' => $device->parameters->firstWhere('key', $key)?->unit, 'timeRange' => '24h'];
                    if (in_array($type, ['chart', 'metrics_over_time', 'metric_by_devices'], true)) $configuration['chartType'] = 'line';
                    if (in_array($type, ['gauge', 'slider'], true)) $configuration += ['minimum' => 0, 'maximum' => 100];
                    if ($type === 'image_map') $configuration += ['imageAssetId' => $mapAsset->id, 'markers' => [['id' => 'sample-device', 'x' => 0.5, 'y' => 0.5, 'deviceId' => $device->id, 'label' => $device->name]]];
                } elseif ($type === 'label') $configuration = ['staticValue' => 'SAMPLE DATA — System operating normally'];
                DashboardWidget::create(['id' => (string) Str::uuid(), 'dashboard_id' => $dashboard->id, 'widget_type' => $type, 'title' => $definition['label'], 'layout' => $layout, 'configuration' => $configuration, 'position' => $position]);
            }
        });
    }
}
