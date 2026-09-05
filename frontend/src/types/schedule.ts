export type ScheduleType =

    | "once"

    | "daily"

    | "weekly"

    | "interval";





export interface AutomationSchedule {


    id?:string;

    automationId?:string;


    type:ScheduleType;


    time?:string;


    day?:string;


    intervalMinutes?:number;

    at?:string;


    enabled:boolean;


}
