import api from "./api";


import type {

    AuditLog

}

from "../types/audit";





export interface AuditLogFilters {

    userId?:string;

    action?:string;

    resourceType?:string;

    startDate?:string;

    endDate?:string;

}





export async function getAuditLogs(

    filters?:AuditLogFilters

){



    const response =

        await api.get<AuditLog[]>(

            "/audit-logs",

            {

                params:filters

            }

        );



    return response.data;

}





export async function getAuditLog(

    id:string

){



    const response =

        await api.get<AuditLog>(

            `/audit-logs/${id}`

        );



    return response.data;

}