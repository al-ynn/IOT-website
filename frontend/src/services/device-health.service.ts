import api from "./api";



export interface DeviceHeartbeat {


    deviceId:string;


    batteryLevel:number;


    signalStrength:number;


}




export async function sendHeartbeat(

data:DeviceHeartbeat

){


const response =

await api.post(

"/devices/heartbeat",

data

);



return response.data;


}






export async function getDeviceHealth(

deviceId:string

){


const response =

await api.get(

`/devices/${deviceId}/health`

);



return response.data;


}