import api from "./api";


import type {

User,

AuthSession

}

from "../types/auth";


import {

saveToken,

removeToken

}

from "./storage.service";







export async function login(

email:string,

password:string

){



const response =

await api.post<AuthSession>(

"/auth/login",

{

email,

password

}

);



saveToken(

response.data.token

);



return response.data;


}







export async function register(

data:{name:string;email:string;password:string}

){



const response =

await api.post<AuthSession>(

"/auth/register",

data

);

saveToken(response.data.token);



return response.data;


}







export async function logout(){



await api.post(

"/auth/logout"

);



removeToken();


}







export async function getCurrentUser(){



const response =

await api.get<User>(

"/auth/me"

);



return response.data;


}
