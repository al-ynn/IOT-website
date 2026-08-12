export interface Organization {


    id:string;


    name:string;


    slug:string;


    description?:string;


    createdAt:string;


}





export interface OrganizationSettings {


    organizationId:string;


    timezone:string;


    language:string;


    emailNotifications:boolean;


}