export type InvitationStatus =


    | "pending"


    | "accepted"


    | "expired"


    | "cancelled";







export interface Invitation {


    id:string;


    organizationId:string;


    email:string;


    role:string;


    status:InvitationStatus;


    invitedBy:string;


    createdAt:string;


    expiresAt:string;


}







export interface OrganizationMember {


    id:string;


    name:string;


    email:string;


    role:string;


    joinedAt:string;


}