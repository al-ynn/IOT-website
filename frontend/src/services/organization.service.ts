import api from "./api";


import type {

Organization,

OrganizationSettings

}

from "../types/organization";







export async function getOrganization(){



const response =

await api.get<Organization>(

"/organization"

);



return response.data;


}







export async function createOrganization(

data:Partial<Organization>

){



const response =

await api.post<Organization>(

"/organization",

data

);



return response.data;


}







export async function updateOrganization(

data:Partial<Organization>

){



const response =

await api.put<Organization>(

"/organization",

data

);



return response.data;


}







export async function getOrganizationSettings(){



const response =

await api.get<OrganizationSettings>(

"/organization/settings"

);



return response.data;


}







export async function updateOrganizationSettings(

data:Partial<OrganizationSettings>

){



const response =

await api.put(

"/organization/settings",

data

);



return response.data;


}