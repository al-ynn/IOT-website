export type TriggerEventType =


    | "telemetry"


    | "device_status"


    | "schedule";





export interface TriggerEvent {


    type:TriggerEventType;


    deviceId:string;


    field:string;


    value:unknown;


    timestamp:string;


}
