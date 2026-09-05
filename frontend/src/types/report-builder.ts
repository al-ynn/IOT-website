export type ReportSectionType =

    | "chart"

    | "metric"

    | "table";





export interface ReportSection {


    id:string;


    title:string;


    type:ReportSectionType;


    config:Record<string,unknown>;


    order:number;


}





export interface ReportBuilderConfig {


    name:string;


    deviceIds:string[];


    metrics:string[];


    sections:ReportSection[];


    createdAt:string;


}
