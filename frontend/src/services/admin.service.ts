import api from "./api";


import type {

    AdminOverview,

    AdminOrganization,

    AdminUser

}

from "../types/admin";





export async function getAdminOverview(){



    const response =

        await api.get<AdminOverview>(

            "/admin/overview"

        );



    return response.data;

}





export async function getAdminOrganizations(){



    const response =

        await api.get<AdminOrganization[]>(

            "/admin/organizations"

        );



    return response.data;

}





export async function updateOrganizationStatus(

    id:string,

    status:"active" | "suspended"

){



    const response =

        await api.patch(

            `/admin/organizations/${id}/status`,

            {

                status

            }

        );



    return response.data;

}





export async function getAdminUsers(){



    const response =

        await api.get<AdminUser[]>(

            "/admin/users"

        );



    return response.data;

}





export async function updateUserStatus(

    id:string,

    status:"active" | "suspended"

){



    const response =

        await api.patch(

            `/admin/users/${id}/status`,

            {

                status

            }

        );



    return response.data;

}