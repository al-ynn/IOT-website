export type WidgetType =

    | "metric"

    | "chart"

    | "gauge"

    | "table"

    | "status"

    | "device"

    | "control"
    | "temperature"

    | "machine-status"

    | "energy";




export interface WidgetDatasource {


    deviceId:string;


    telemetryKey:string;


    unit?:string;


}




export interface WidgetSettings {


    title:string;


    description?:string;


    datasource?:WidgetDatasource;


    refreshRate?:number;

    chartType?:"line"|"area"|"bar";

    minimum?:number;

    maximum?:number;


    threshold?:{


        warning?:number;


        critical?:number;


    };


}





export interface Widget {


    id:string;


    type:WidgetType;


    settings:WidgetSettings;


    layout:{


        x:number;


        y:number;


        w:number;


        h:number;


    };


}





export interface Dashboard {


    id?:string;


    name:string;


    description?:string;


    widgets:Widget[];


}
