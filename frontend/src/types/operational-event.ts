export type OperationalEventSeverity="info"|"warning"|"error";
export type OperationalEventSource="automation"|"provisioning"|"firmware"|"webhook"|"device";
export type OperationalEventType="automation_execution_failed"|"provisioning_session_failed"|"provisioning_session_expired"|"firmware_delivery_unavailable"|"webhook_delivery_failed"|"device_crash_reported";
export interface OperationalEvent{ id:string;source:OperationalEventSource;eventType:OperationalEventType;severity:OperationalEventSeverity;title:string|null;message:string;context:Record<string,unknown>;occurredAt:string;device:{id:string;name:string|null;available:boolean;template?:{id:string;name:string}|null}|null;organization:{id:string;name:string};automation:{id:string;name:string|null;available:boolean}|null }
export interface OperationalEventFilters{search?:string;severity?:OperationalEventSeverity;source?:OperationalEventSource;event_type?:OperationalEventType;device_id?:string;from?:string;to?:string;page?:number;per_page?:25|50|100}
export interface OperationalEventListResponse{data:OperationalEvent[];meta:{current_page:number;last_page:number;per_page:number;total:number}}
