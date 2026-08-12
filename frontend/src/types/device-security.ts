export type CredentialStatus =

    | "active"

    | "expired"

    | "revoked";





export interface DeviceCertificate {


    id:string;


    deviceId:string;


    certificateName:string;


    issuedDate:string;


    expiryDate:string;


    status:CredentialStatus;


}





export interface DeviceToken {


    id:string;


    deviceId:string;


    tokenName:string;


    createdAt:string;


    lastUsed?:string;


    status:CredentialStatus;


}





export interface DeviceSecurityScore {


    deviceId:string;


    score:number;


    risks:string[];


}