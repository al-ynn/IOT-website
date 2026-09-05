export type DisabledResourceType="device"|"device_template";
export type DisabledLifecycleState="disabled"|"archived";
export interface DisabledActor{id:string;name:string;inactive?:boolean}
export interface AdminDisabledResource{resourceType:DisabledResourceType;resourceId:string;resourceLabel:string;organization:{id:string;name:string}|null;lifecycle:DisabledLifecycleState;disabledAt:string|null;archivedAt:string|null;disabledBy:DisabledActor|null;creator:DisabledActor|null;createdAt:string|null;needsAttention:boolean;publication:{version:number}|null;accessSummary:{label:"assignments"|"collaborators";count:number};capabilities:{canRestore:boolean;restoreBlocker:string|null};adminDeepLink:string}
export interface AdminDisabledPage{data:AdminDisabledResource[];current_page:number;last_page:number;per_page:number;total:number}
export interface AdminDisabledSummary{totalDisabled:number;disabledByType:Record<DisabledResourceType,number>;totalArchived:number}
export interface AdminDisabledFilters{resource_type?:DisabledResourceType;organization_id?:string;disabled_by?:string;needs_attention?:boolean;has_publication?:boolean;state?:DisabledLifecycleState;search?:string;sort?:"newest"|"oldest"|"name";page?:number;per_page?:25|50}
