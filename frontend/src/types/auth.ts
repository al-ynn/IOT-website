export type OrganizationRole =
    | "owner"
    | "admin"
    | "engineer"
    | "operator"
    | "viewer";


export type PlatformRole =
    | "platform_admin"
    | null;


export interface User {

    id:string;

    name:string;

    email:string;

    organizationId:string;

    role:OrganizationRole;

    platformRole:PlatformRole;

    createdAt:string;

}




export interface AuthSession {


    token:string;


    user:User;


    expiresAt?:string;


}
