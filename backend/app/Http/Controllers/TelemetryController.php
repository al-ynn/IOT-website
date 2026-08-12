<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;

use App\Events\TelemetryUpdated;
use App\Models\TelemetryRecord;
use App\Services\Automation\AutomationTriggerEngine;



class TelemetryController extends Controller
{


    public function store(Request $request, AutomationTriggerEngine $automation)
    {

        abort_unless(
            $request->user()?->organization
            && $request->user()->hasOrganizationPermission('device.manage'),
            403
        );


        $data = $request->validate([


            'device_id'=>'required|string|max:255',


            'key'=>'required|string|max:100',


            'value'=>'required|numeric',


            'unit'=>'nullable|string|max:50'


        ]);

        abort_unless($request->user()?->organization?->devices()->whereKey($data['device_id'])->exists(), 404, 'Device not found.');




        $record = TelemetryRecord::create([
            'device_id' => $data['device_id'],
            'key' => $data['key'],
            'value' => $data['value'],
            'unit' => $data['unit'] ?? null,
            'recorded_at' => now(),
        ]);

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
