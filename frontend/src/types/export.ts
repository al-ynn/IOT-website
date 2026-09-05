export type ExportFormat =


    | "pdf"


    | "csv"


    | "excel";





export interface ExportRequest {


    reportId:string;


    format:ExportFormat;


}





export interface ExportFile {


    id:string;


    name:string;


    format:ExportFormat;


    url:string;


    createdAt:string;


}