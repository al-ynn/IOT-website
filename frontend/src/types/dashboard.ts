export type CoreWidgetType="switch"|"slider"|"label"|"device_count"|"device_table"|"geo_map"|"image_map"|"device_connection_map"|"metrics_over_time"|"metric_by_devices"|"event_count_tile"|"latest_events"|"event_count_chart"|"events_over_time"|"events_breakdown_over_time"|"events_by_organization"|"events_by_device"|"events_by_template"|"activations";
export type LegacyWidgetType="metric"|"chart"|"gauge"|"table"|"status"|"device"|"global_device_summary"|"global_failure_summary";
export type WidgetType=CoreWidgetType|LegacyWidgetType;
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

    timeRange?:"1h"|"6h"|"24h"|"7d"|"30d";

    chartType?:"line"|"area"|"bar";

    minimum?:number;

    maximum?:number;
    staticValue?:string;
    aggregation?:"average"|"minimum"|"maximum"|"sum"|"count";
    rowLimit?:10|25|50;
    deviceIds?:string[];
    sourceMode?:"explicit_devices"|"template_devices"|"authorized_device_set";
    deviceTemplateId?:string;
    eventType?:string;
    imageAssetId?:string;
    markers?:Array<{id:string;x:number;y:number;deviceId?:string;label?:string}>;
    step?:number;


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

    available?:boolean;


}





export interface Dashboard {


    id?:string;


    name:string;


    description?:string;


    widgets:Widget[];

    scope?:"personal"|"admin_global"|"device"|"published";

    deviceId?:string|null;

    canEdit?:boolean;

    canShare?:boolean;

    isDefault?:boolean;

    layoutVersion?:number;

    widgetDefinitions?:DashboardWidgetDefinition[];
    revisionState?:import("./pull-update").ResourceRevisionState;
    baseRevisionId?:string;


}

export interface DashboardWidgetDefinition {type:WidgetType;label:string;description:string;category:string;contexts:Array<"personal"|"admin_global"|"device">;scope:"personal"|"admin_global"|"device";dataContract:string;configSchemaVersion:number;defaultSettings:Partial<WidgetSettings>;defaultLayout:Widget["layout"];minWidth:number;minHeight:number;maxWidth:number;maxHeight:number;resizable:boolean;removable:boolean;duplicable:boolean;requiresDevice:boolean;requiresExistingSourceConfiguration:boolean;dateRanges:string[]}
