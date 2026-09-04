export type NotificationCategory="updates"|"requests"|"approvals"|"mentions"|"system";
export type NotificationStateFilter="all"|"unread"|"action_required"|"dismissed";
export type NotificationActionStatus="none"|"available"|"required"|"resolved"|"no_longer_available";
export interface NotificationAction{key:string;kind:"navigate"|"workflow";label:string;href:string;requiresConfirmation:boolean}
export interface AppNotification{id:string;schemaVersion:number;eventType:string;category:NotificationCategory;title:string;body:string;actor:{id:string;displayName:string}|null;resource:{type:string;id:string;label:string|null}|null;organization:{id:string;label:string|null};actions:NotificationAction[];deepLink:string|null;requiresAction:boolean;actionStatus:NotificationActionStatus;actionState:string|null;isRead:boolean;readAt:string|null;isDismissed:boolean;dismissedAt:string|null;createdAt:string}
export interface NotificationListResponse{data:AppNotification[];meta:{current_page:number;last_page:number;per_page:number;total:number}}
