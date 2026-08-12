import api from "./api";




export async function getLifecycleHistory(

deviceId:string

){


const response =

await api.get(

`/devices/${deviceId}/lifecycle`

);



return response.data;


}





export async function updateDeviceStatus(

deviceId:string,

status:string,

description:string

){


const response =

await api.post(

`/devices/${deviceId}/lifecycle`,

{


status,


description


}

);



return response.data;


}





export async function getMaintenanceHistory(

deviceId:string

){


const response =

await api.get(

`/devices/${deviceId}/maintenance`

);



return response.data;


}