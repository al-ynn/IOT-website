export type FirmwareStatus =

    | "draft"

    | "released"

    | "deprecated";





export interface Firmware {


    id:string;


    name:string;


    version:string;


    description:string;


    fileUrl:string;


    status:FirmwareStatus;


    createdAt:string;


}





export interface FirmwareUpdate {


    id:string;


    deviceId:string;


    firmwareId:string;


    status:


        | "pending"

        | "downloading"

        | "installing"

        | "completed"

        | "failed";



    progress:number;


}