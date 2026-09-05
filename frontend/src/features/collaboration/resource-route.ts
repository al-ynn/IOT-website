import type{CollaborationResourceReference}from"./types";
export type ResourceRouteContext="app"|"admin";
export function canonicalResourceRoute(resource:CollaborationResourceReference,context:ResourceRouteContext="app",options?:{threadId?:string}){if(resource.type!=="device")return null;const base=context==="admin"?`/admin/devices/${resource.id}`:`/app/devices/${resource.id}`;return options?.threadId?`${base}?tab=comments&thread=${encodeURIComponent(options.threadId)}`:base;}
