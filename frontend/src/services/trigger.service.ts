import api from "./api";


import type {

TriggerEvent

}

from "../types/trigger";





export async function sendTriggerEvent(

event:TriggerEvent

){


const response =

await api.post(

"/automation/events",

event

);



return response.data;


}





export async function getTriggerEvents(){


const response =

await api.get(

"/automation/events"

);



return response.data;


}