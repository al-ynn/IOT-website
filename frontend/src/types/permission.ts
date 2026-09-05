export interface Permission {


    id:string;


    name:string;


    description:string;


    category:string;


}







export interface RolePermission {


    roleId:string;


    permissionId:string;


}