export type AttentionResourceType="device"|"device_template";
export type AttentionReason="configuration_follow_up"|"operational_follow_up"|"governance_follow_up"|"data_quality_follow_up"|"other";
export interface AttentionState{attentionId:string;resourceType:AttentionResourceType;resourceId:string;resourceLabel:string;organization:{id:string;name:string}|null;reasonCode:AttentionReason;reasonLabel:string;note:string;status:"open"|"resolved";markedBy:{id:string;name:string;inactive:boolean}|null;markedAt:string;updatedAt:string;resourceLifecycle:string;capabilities:{canUpdate:boolean;canClear:boolean;canAct:boolean};adminDeepLink:string;appDeepLink:string}
export interface AttentionPage{data:AttentionState[];current_page:number;last_page:number;per_page:number;total:number}
