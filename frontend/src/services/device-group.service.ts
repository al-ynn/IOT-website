import api from "./api";


import type {

DeviceGroup

}

from "../types/device-group";





export async function getGroups(){


const response =

await api.get<DeviceGroup[]>(

"/device-groups"

);



return response.data;


}





export async function createGroup(

group:Partial<DeviceGroup>

){


const response =

await api.post(

"/device-groups",

group

);



return response.data;


}





export async function addDevicesToGroup(

groupId:string,

deviceIds:string[]

){


const response =

await api.post(

`/device-groups/${groupId}/devices`,

{


devices:deviceIds


}

);



return response.data;


}





export async function bulkAction(

groupId:string,

action:string

){


const response =

await api.post(

`/device-groups/${groupId}/action`,

{


action


}

);



return response.data;


}