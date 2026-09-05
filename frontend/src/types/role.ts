export type SystemRole =


    | "owner"


    | "admin"


    | "engineer"


    | "operator"


    | "viewer";







export interface Role {


    id:string;


    name:string;


    description:string;


    organizationId:string;


    isSystemRole:boolean;


    createdAt:string;


}





export interface UserRoleAssignment {


    userId:string;


    roleId:string;


}