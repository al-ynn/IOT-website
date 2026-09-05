export type AuditAction =

    | "created"

    | "updated"

    | "deleted"

    | "executed"

    | "login"

    | "logout"

    | "invited"

    | "removed";



export interface AuditLog {

    id:string;

    organizationId:string;

    userId:string;

    userName:string;

    action:AuditAction;

    resourceType:string;

    resourceId?:string;

    resourceName?:string;

    description:string;

    metadata?:Record<string,unknown>;

    ipAddress?:string;

    createdAt:string;

}