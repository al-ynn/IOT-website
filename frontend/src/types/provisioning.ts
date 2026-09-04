export type ProvisioningStatus="pending"|"completed"|"failed"|"expired"|"cancelled";
export interface ProvisioningSession{ id:string;name:string|null;status:ProvisioningStatus;organization:{id:string;name:string};initiatedBy:{id:string;name:string;email:string}|null;device:{id:string;name:string;identifier:string}|null;template:{id:string;name:string}|null;startedAt:string;expiresAt:string;completedAt:string|null;failedAt:string|null;failureCode:string|null;failureMessage:string|null;createdAt:string;updatedAt:string }
export interface ProvisioningListResponse{data:ProvisioningSession[];meta:{current_page:number;last_page:number;per_page:number;total:number}}
export interface ProvisioningFilters{search?:string;status?:ProvisioningStatus;template_id?:string;page?:number;per_page?:25|50|100}
export interface ProvisioningCompletionPayload{name:string;serialNumber:string;type:string;protocol:string;macAddress?:string|null}
