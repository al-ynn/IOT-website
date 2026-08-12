import api from "./api";


import type {
    AutomationTemplate
}
from "../types/automation-template";
import type { AutomationRule } from "../types/automation";





export async function getTemplates(){

    const response =

    await api.get<AutomationTemplate[]>(

        "/automation/templates"

    );


    return response.data;

}





export async function getTemplate(

    id:string

){

    const response =

    await api.get<AutomationTemplate>(

        `/automation/templates/${id}`

    );


    return response.data;

}





export async function createAutomationFromTemplate(

    templateId:string

){

    const response =

    await api.post<AutomationRule>(

        `/automation/templates/${templateId}/create`

    );


    return response.data;

}





export async function createTemplate(

    template:Pick<AutomationTemplate,"name"|"description"|"category"|"definition">

){

    const response =

    await api.post(

        "/automation/templates",

        template

    );


    return response.data;

}





export async function updateTemplate(

    id:string,

    template:Partial<Pick<AutomationTemplate,"name"|"description"|"category"|"definition">>

){

    const response =

    await api.put(

        `/automation/templates/${id}`,

        template

    );


    return response.data;

}





export async function deleteTemplate(

    id:string

){

    const response =

    await api.delete(

        `/automation/templates/${id}`

    );


    return response.data;

}
