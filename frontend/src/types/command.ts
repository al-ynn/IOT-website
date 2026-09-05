export type CommandStatus =

    | "pending"

    | "sent"

    | "completed"

    | "failed";




export interface DeviceCommand {


    id:string;


    deviceId:string;


    command:string;


    payload?:Record<string,unknown>;


    status:CommandStatus;


    createdAt:string;


    completedAt?:string;


}
