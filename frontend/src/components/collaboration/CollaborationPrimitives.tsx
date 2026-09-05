import type {ReactNode} from "react";
import {Badge} from "../ui";
export type SafeIdentity={displayName?:string|null;name?:string|null;state?:"active"|"inactive";inactive?:boolean};
export function SafeUserIdentity({identity,fallback="Former user",context}:{identity?:SafeIdentity|null;fallback?:string;context?:string}){const name=identity?.displayName??identity?.name??fallback;const inactive=identity?.state==="inactive"||identity?.inactive===true;return <span>{context&&<span className="text-[var(--ds-text-muted)]">{context} </span>}<span>{name}</span>{inactive&&<span className="text-[var(--ds-text-muted)]"> (Inactive)</span>}</span>;}
export function RelativeTimestamp({value}:{value:string}){const date=new Date(value),exact=Number.isNaN(date.valueOf())?value:date.toLocaleString();return <time dateTime={value} title={exact}>{exact}</time>;}
export function DeviceAccessBadge({access}:{access:"viewer"|"full_access"}){return <Badge tone={access==="full_access"?"success":"info"}>{access==="full_access"?"Full Access":"Viewer"}</Badge>;}
export function CollaborationAccessBadge({access}:{access:"view"|"edit"}){return <Badge tone={access==="edit"?"success":"info"}>{access==="edit"?"Edit":"View"}</Badge>;}
export function ResourceLifecycleBadge({state}:{state:"active"|"disabled"|"archived"}){const tone=state==="active"?"success":state==="disabled"?"warning":"neutral";return <Badge tone={tone}>{state[0].toUpperCase()+state.slice(1)}</Badge>;}
export function CreatorAttribution({identity}:{identity?:SafeIdentity|null}){return <SafeUserIdentity context="Created by" identity={identity} fallback="Creator unknown"/>;}
export function ContributorAttribution({identity}:{identity?:SafeIdentity|null}){return <SafeUserIdentity context="Contributed by" identity={identity} fallback="System baseline"/>;}
export function CollaborationState({kind,children}:{kind:"empty"|"loading"|"error";children:ReactNode}){return <p role={kind==="error"?"alert":"status"} aria-live="polite" className="rounded-lg border border-[var(--ds-border-subtle)] p-4 text-sm text-[var(--ds-text-muted)]">{children}</p>;}
