export type DeviceSharePermission="viewer"|"full_access";
export type CollaborationPermission="view"|"edit";
export type SharePermission=DeviceSharePermission|CollaborationPermission;
export type ShareResourceType="device"|"device_template"|"dashboard"|"automation"|"report"|"location";
export type ShareStatus="pending_recipient"|"awaiting_admin_approval"|"declined"|"approved"|"rejected"|"cancelled"|"revoked";
export interface ShareRequest{ id:number;resource:{type:ShareResourceType;id:number;name:string|null;organization:string|null};sender:{id:number;name:string}|null;recipient:{id:number;name:string;email:string}|null;requested_permission:SharePermission;final_permission:SharePermission|null;status:ShareStatus;note:string|null;created_at:string; }
export interface ShareCandidate{id:number;name:string;email:string;existing_access:SharePermission|null}
export interface PaginatedShares{data:ShareRequest[];current_page:number;last_page:number;total:number}export interface DashboardShareRecipient{id:number;recipient:{id:number;name:string;email:string}|null;permission:"view"|"edit";status:ShareStatus;createdAt:string;acceptedAt:string|null}