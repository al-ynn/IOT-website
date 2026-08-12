export type ReportType =

    | "device_performance"

    | "telemetry"

    | "energy";





export interface ReportFilter {


    deviceIds:string[];


    metrics:string[];


    startDate:string;


    endDate:string;


}





export interface Report {


    id:string;


    name:string;


    type:ReportType;


    filter:ReportFilter;


    createdAt:string;


}