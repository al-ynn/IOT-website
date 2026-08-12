import api from "./api";


import type {

Device

}

from "../types/device";





export async function getDevices(){


const response =

await api.get<Device[]>(

"/devices"

);



return response.data;


}





export async function getDevice(

id:string

){


const response =

await api.get<Device>(

`/devices/${id}`

);



return response.data;


}





export async function createDevice(

device:Device

){


const response =

await api.post(

"/devices",

device

);



return response.data;


}


export async function registerDevice(

device:Device

){


const response =

await api.post(

"/devices/register",

device

);



return response.data;


}


export async function deleteDevice(

id:string

){


return api.delete(

`/devices/${id}`

);


}