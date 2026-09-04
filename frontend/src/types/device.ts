import type {LocationReference} from "./location";

export type DeviceStatus =

    | "online"

    | "offline"

    | "maintenance"

    | "inactive";





export interface Device {


    id?:string;
    canonicalId?:string;
    baseRevisionId?:string|null;
    saveOutcome?:{replayed:boolean;changed:boolean;committedRevisionId:string|null;committedRevisionNumber:number|null;completedAt:string|null}|null;


    name:string;


    type:string;


    serialNumber:string;


    status?:DeviceStatus;


    location?:LocationReference|null;


    lastSeen?:string;


    battery?:number;


    firmwareVersion?:string;


    protocol:string;


    macAddress?:string;

    createdAt?:string;
    creator?:{id:string;name:string}|null;
    template?:{id:string;name:string}|null;

    access?: {
        level:"viewer"|"full_access";
        canManage:boolean;
    };
    capabilities?:{canView:boolean;canEdit:boolean;canChangeLocation?:boolean;canShare:boolean;canEditDashboard:boolean;canEditParameters:boolean;canManageCredentials:boolean;canManageAccess:boolean;canViewAdminMetadata:boolean};


    health?: {


        signalStrength?:number;


        batteryLevel?:number;


        lastHeartbeat?:string;


        uptime?:number;


    };


}

export interface CreateDeviceRequest {name:string;type:string;serialNumber:string;protocol:string;location_id?:string|null;macAddress?:string;template_id?:string}

export type DeviceParameterDataType="number"|"integer"|"string"|"boolean"|"enum";
export type DeviceParameterSemantic="temperature"|"humidity"|"pressure"|"voltage"|"current"|"power"|"battery"|"motion"|"state"|"signal"|"level";
export interface DeviceParameterConfiguration{min?:number;max?:number;precision?:number;step?:number;maxLength?:number;default?:string|number|boolean;trueLabel?:string;falseLabel?:string;options?:string[]}
export interface DeviceParameter{id:string;deviceId:string;name:string;key:string;dataType:DeviceParameterDataType;unit:string|null;description:string|null;semantic?:DeviceParameterSemantic|null;configuration?:DeviceParameterConfiguration;latestValue?:string|number|boolean|null;latestRecordedAt?:string|null;createdAt:string;updatedAt:string}
export interface DeviceParameterPayload{name:string;key:string;data_type:DeviceParameterDataType;unit?:string|null;description?:string|null;semantic?:DeviceParameterSemantic|null;configuration?:DeviceParameterConfiguration}
export interface DeviceTemplateParameter{id:string;name:string;key:string;dataType:DeviceParameterDataType;unit:string|null;description:string|null;semantic?:DeviceParameterSemantic|null;configuration?:DeviceParameterConfiguration}
export interface DeviceTemplate{id:string;name:string;description:string|null;deviceType:string|null;protocol:string|null;organization?:{id:string;name:string};creator?:{id:string;name:string}|null;permission?:"view"|"edit"|"admin"|null;capabilities?:{canView:boolean;canEdit:boolean;canChangeLocation?:boolean;canShare:boolean;canComment:boolean;canViewRevisions:boolean};parameterCount:number;deviceCount:number;dashboardConfigured:boolean;parameters?:DeviceTemplateParameter[];metadataDefinitions?:DeviceTemplateMetadataDefinition[];eventDefinitions?:DeviceTemplateEventDefinition[];updatedAt:string}
export interface DeviceTemplateMetadataDefinition{id:string;name:string;key:string;dataType:string;description:string|null;required:boolean;configuration?:Record<string,unknown>}
export interface DeviceTemplateEventDefinition{id:string;name:string;code:string;severity:string;description:string|null;enabled:boolean}
export interface DeviceTemplatePayload{name:string;description?:string|null;device_type:string;protocol:string}
