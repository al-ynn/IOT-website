import api from "./api";

import type { AutomationSchedule } from "../types/schedule";





export async function createSchedule(

data:AutomationSchedule & {automationId:string}

){


const response =

await api.post(

"/automation/schedules",

data

);



return response.data;


}





export async function getSchedules(){


const response =

await api.get(

"/automation/schedules"

);



return response.data;


}





export async function deleteSchedule(

id:string

){


await api.delete(

`/automation/schedules/${id}`

);


}
