export type DeviceStatus =

    | "online"

    | "offline"

    | "maintenance"

    | "inactive";





export interface Device {


    id?:string;


    name:string;


    type:string;


    serialNumber:string;


    status?:DeviceStatus;


    location?:string;


    lastSeen?:string;


    battery?:number;


    firmwareVersion?:string;


    protocol:string;


    macAddress?:string;


    credentials?:{


        deviceId:string;


        accessToken:string;


        apiKey:string;


    };

    health?: {


        signalStrength?:number;


        batteryLevel?:number;


        lastHeartbeat?:string;


        uptime?:number;


    };


}