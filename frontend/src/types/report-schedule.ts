export type ScheduleFrequency =

    | "daily"

    | "weekly"

    | "monthly";





export interface ReportSchedule {


    id:string;


    reportId:string;


    name:string;


    frequency:ScheduleFrequency;


    time:string;


    email?:string;


    enabled:boolean;


    createdAt:string;


}