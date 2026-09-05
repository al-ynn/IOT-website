export type FleetAccessLevel="viewer"|"full_access"|null;
export interface FleetDevice{id:string;name:string;identifier:string|null;status:string;type:string;protocol:string;lastSeen:string|null;location:null;template:{id:string;name:string}|null;access:{level:FleetAccessLevel;label:string;canManage:boolean}}
export interface FleetSummary{visibleDevices:number;online:number;offline:number;fullAccess:number;viewer:number;templatesRepresented:number}
export interface FleetFilters{search?:string;status?:"online"|"offline"|"maintenance"|"inactive";device_template_id?:string;access_level?:"viewer"|"full_access";device_type?:string;protocol?:string;sort?:"name"|"identifier"|"status"|"last_seen"|"created_at";direction?:"asc"|"desc";page?:number;per_page?:25|50|100}
export interface FleetListResponse{data:FleetDevice[];meta:{current_page:number;last_page:number;per_page:number;total:number};summary:FleetSummary;filterOptions:{templates:{id:string;name:string}[]}}
