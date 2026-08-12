export interface DevicePerformance {


    deviceId:string;


    uptime:number;


    downtime:number;


    responseTime:number;


    errorCount:number;


    healthScore:number;


}





export interface DeviceError {


    id:string;


    deviceId:string;


    message:string;


    timestamp:string;


}