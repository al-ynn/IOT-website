import api from "./api";


import type {

Firmware

}

from "../types/firmware";





export async function getFirmware(){


const response =

await api.get<Firmware[]>(

"/firmware"

);



return response.data;


}





export async function uploadFirmware(

data:FormData

){


const response =

await api.post(

"/firmware/upload",

data,

{


headers:{


"Content-Type":"multipart/form-data"


}


}

);



return response.data;


}





export async function updateDeviceFirmware(

deviceId:string,

firmwareId:string

){


const response =

await api.post(

"/firmware/update",

{


deviceId,

firmwareId


}

);



return response.data;


}





export async function getFirmwareHistory(

deviceId:string

){


const response =

await api.get(

`/devices/${deviceId}/firmware`

);



return response.data;


}