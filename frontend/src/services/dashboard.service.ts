import api from "./api";


import type {

Dashboard

}

from "../types/dashboard";




export async function getDashboards(){


const response =

await api.get<Dashboard[]>(

"/dashboards"

);



return response.data;


}




export async function createDashboard(

dashboard:Dashboard

){


const response =

await api.post(

"/dashboards",

dashboard

);



return response.data;


}





export async function getDashboard(

id:string

){


const response =

await api.get<Dashboard>(

`/dashboards/${id}`

);



return response.data;


}





export async function updateDashboard(

id:string,

dashboard:Dashboard

){


const response =

await api.put(

`/dashboards/${id}`,

dashboard

);



return response.data;


}





export async function deleteDashboard(

id:string

){


return api.delete(

`/dashboards/${id}`

);


}





export async function duplicateDashboard(

id:string

){


const response =

await api.post(

`/dashboards/${id}/duplicate`

);



return response.data;


}
