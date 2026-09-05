import api from "./api";


import type {

AutomationLog

}

from "../types/automation-log";





export async function getAutomationLogs(){


const response =

await api.get<{data:AutomationLog[]}>(

"/automation/logs"

);



return response.data.data;


}





export async function getAutomationLog(

id:string

){


const response =

await api.get<AutomationLog>(

`/automation/logs/${id}`

);



return response.data;


}





