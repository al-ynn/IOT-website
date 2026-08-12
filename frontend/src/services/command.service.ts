import api from "./api";


import type {

DeviceCommand

}

from "../types/command";





export async function sendCommand(

command:Partial<DeviceCommand>

){


const response =

await api.post(

"/commands",

command

);



return response.data;


}





export async function getCommandHistory(

deviceId:string

){


const response =

await api.get<DeviceCommand[]>(

`/devices/${deviceId}/commands`

);



return response.data;


}