import api from "./api";



export async function generateDeviceClaim(

deviceId:string

){


const response =

await api.post(

"/devices/claim",

{

deviceId

}

);



return response.data;


}





export async function claimDevice(

token:string

){


const response =

await api.post(

"/devices/claim/verify",

{

token

}

);



return response.data;


}