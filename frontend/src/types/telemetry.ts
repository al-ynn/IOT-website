export interface Device {

    id:string;


    name:string;


    type:string;

}





export interface Telemetry {

    id:string;


    deviceId:string;


    key:string;


    value:number;


    unit?:string;


    timestamp:string;

}





export interface TelemetryRecord {

    id:string;


    deviceId:string;


    key:string;


    value:number;


    unit:string;


    recordedAt:string;

}





/*
    Used by analytics engine.

    Converts telemetry data into
    metric-based analytics format.
*/

export interface TelemetryPoint {

    deviceId:string;


    metric:string;


    value:number;


    timestamp:string;

}





/*
    Used for aggregated telemetry.

    Example:

    Temperature average:
    72°C

    Minimum:
    60°C

    Maximum:
    85°C

*/

export interface TelemetryAggregate {

    deviceId:string;


    metric:string;


    period:string;


    average:number;


    minimum:number;


    maximum:number;


    count:number;

}