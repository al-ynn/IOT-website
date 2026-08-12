import api from "./api";


import type {

Workflow

}

from "../types/workflow";





export async function getWorkflows(){


const response =

await api.get<Workflow[]>(

"/workflows"

);



return response.data;


}





export async function createWorkflow(

workflow:Partial<Workflow>

){


const response =

await api.post(

"/workflows",

workflow

);



return response.data;

}





export async function executeWorkflow(

id:string

){


const response =

await api.post(

`/workflows/${id}/execute`

);



return response.data;


}