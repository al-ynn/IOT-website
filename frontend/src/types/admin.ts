export type OrganizationStatus =
    | "active"
    | "suspended"
    | "pending";


export interface AdminOrganization {

    id:string;

    name:string;

    slug:string;

    ownerName:string;

    ownerEmail:string;

    memberCount:number;

    deviceCount:number;

    status:OrganizationStatus;

    createdAt:string;

}


export interface AdminUser {

    id:string;

    name:string;

    email:string;

    organizationId:string;

    organizationName:string;

    role:string;

    status:"active" | "suspended";

    lastLoginAt?:string;

    createdAt:string;

}


export interface AdminOverview {

    organizations:number;

    activeOrganizations:number;

    users:number;

    devices:number;

    activeAlerts:number;

}