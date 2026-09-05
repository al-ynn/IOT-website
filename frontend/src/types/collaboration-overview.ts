import type{ActivityEvent}from"../services/activity.service";
import type{MyWorkItem}from"./my-work";
import type{SharedResource}from"./shared-with-me";
import type{AppNotification}from"./notification";
export interface OverviewSection<T>{key:string;title:string;status:"available";count:number|null;preview:T[];destination:string}
export interface ChangePreview{resourceType:string;resourceId:string;lifecycle:string;resource:{id:string;name:string;type:string};latestRevision:{revisionNumber:number;createdAt:string;createdBy:{id:string;name:string}|null};pendingRevisionCount:number}
export interface CollaborationOverview{context:"app_personal";sections:{myWork:OverviewSection<MyWorkItem>;sharedWithMe:OverviewSection<SharedResource>;changes:OverviewSection<ChangePreview>;notifications:OverviewSection<AppNotification>;activity:OverviewSection<ActivityEvent>;search:OverviewSection<never>};adminShortcuts:{label:string;destination:string}[]}
