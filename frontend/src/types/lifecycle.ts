export type DeviceLifecycleStatus =


    | "created"


    | "provisioned"


    | "installed"


    | "active"


    | "maintenance"


    | "suspended"


    | "retired";





export interface LifecycleEvent {


    id:string;


    deviceId:string;


    status:DeviceLifecycleStatus;


    description:string;


    performedBy:string;


    createdAt:string;


}





export interface MaintenanceRecord {


    id:string;


    deviceId:string;


    title:string;


    description:string;


    technician:string;


    date:string;


}