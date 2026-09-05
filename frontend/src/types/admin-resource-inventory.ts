export type InventoryLifecycle="active"|"disabled"|"archived";
export type InventoryResourceType="device"|"device_template"|"automation"|"report"|"webhook"|"location"|"firmware";
export interface InventoryResourceTypeOption{key:InventoryResourceType;label:string}
export type AdminViewState="not_viewed"|"viewed"|"updated_since_view";
export interface AdminInventoryRow{resourceType:InventoryResourceType;resourceTypeLabel:string;resourceId:string;label:string;organization:{id:string;name:string}|null;lifecycle:InventoryLifecycle;createdAt:string|null;creator:{id:string;name:string;inactive:boolean}|null;adminDestination:string;viewState:AdminViewState;firstViewedAt:string|null;lastViewedAt:string|null;capabilities:{canOpen:boolean;canDisable:boolean;canRestore:boolean}}
export interface AdminInventoryPage{data:AdminInventoryRow[];current_page:number;last_page:number;per_page:number;total:number}
export interface AdminInventoryMetadata{resourceTypes:InventoryResourceTypeOption[];lifecycles:InventoryLifecycle[]}
export interface AdminInventoryFilters{resource_type?:InventoryResourceType;organization_id?:string;lifecycle?:InventoryLifecycle;q?:string;sort?:"label"|"type";page?:number;per_page?:25|50|100}
