import api from "./api";


import type {

Invitation,

OrganizationMember

}

from "../types/invitation";







export async function getMembers(){


const response =

await api.get<OrganizationMember[]>(

"/organization/members"

);



return response.data;


}







export async function getInvitations(){


const response =

await api.get<Invitation[]>(

"/organization/invitations"

);



return response.data;


}







export async function inviteMember(

data:{

email:string;

role:string;

}

){



const response =

await api.post(

"/organization/invitations",

data

);



return response.data;


}







export async function cancelInvitation(

id:string

){



await api.delete(

`/organization/invitations/${id}`

);


}







export async function acceptInvitation(

token:string

){



const response =

await api.post(

"/invitations/accept",

{

token

}

);



return response.data;


}