import api from "./api";





export async function getDeviceCertificates(

deviceId:string

){


const response =

await api.get(

`/devices/${deviceId}/certificates`

);



return response.data;


}





export async function getDeviceTokens(

deviceId:string

){


const response =

await api.get(

`/devices/${deviceId}/tokens`

);



return response.data;


}





export async function rotateToken(

deviceId:string

){


const response =

await api.post(

`/devices/${deviceId}/tokens/rotate`

);



return response.data;


}





export async function revokeCredential(

id:string

){


const response =

await api.post(

`/credentials/${id}/revoke`

);



return response.data;


}