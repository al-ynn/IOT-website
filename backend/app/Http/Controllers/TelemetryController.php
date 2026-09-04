<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;

use App\Events\TelemetryUpdated;
use App\Services\Admin\DeviceAccessService;
use App\Services\Automation\AutomationTriggerEngine;
use App\Services\TelemetrySchemaService;



class TelemetryController extends Controller
{


    public function store(Request $request, AutomationTriggerEngine $automation, DeviceAccessService $access, TelemetrySchemaService $schema)
    {

        abort_unless(
            $request->user()?->organization
            && $request->user()->hasOrganizationPermission('device.view'),
            403
        );


        $data = $request->validate([


            'device_id'=>'required|string|max:255',


            'key'=>'required|string|max:100',


            'value'=>'present',


            'unit'=>'nullable|string|max:50'


        ]);

        $device = $access->findManageableDeviceOrFail($request->user(), $data['device_id']);
        app(\App\Services\ResourceLifecycleService::class)->assertActive('device', $device->id, 'Disabled or Archived Devices cannot accept telemetry.');




        $record = $schema->record($device, $data['key'], $data['value']);

        event(

            new TelemetryUpdated($data)

        );

        $automation->dispatch($request->user()->organization,'telemetry',['deviceId'=>(string)$data['device_id'],'field'=>$data['key'],$data['key']=>$data['value'],'value'=>$data['value'],'timestamp'=>now()->toISOString()]);




        return response()->json([

            'message'=>'Telemetry received',

            'data'=>[...$data, 'recorded_at' => $record->recorded_at->toISOString()]

        ]);



    }


}
