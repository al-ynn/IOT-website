import type { AutomationAction } from "./action";
import type { ConditionGroup } from "./condition";
import type { AutomationSchedule } from "./schedule";
export type TriggerType="telemetry"|"device_status"|"schedule"|"manual";
export interface AutomationTrigger{type:TriggerType;deviceId?:string;field?:string;}
export type AutomationStatus="draft"|"active"|"disabled";
export interface AutomationRule{id:string;name:string;description?:string;status:AutomationStatus;enabled:boolean;version:number;baseRevisionId?:string|null;saveOutcome?:{replayed:boolean;changed:boolean;committedRevisionId:string|null;committedRevisionNumber:number|null;completedAt:string|null}|null;trigger:AutomationTrigger;triggerDevice?:{id:string;name:string|null;available:boolean}|null;conditions:ConditionGroup;actions:AutomationAction[];schedule?:AutomationSchedule|null;lastExecutedAt?:string;createdAt:string;updatedAt:string;}
export interface AutomationDefinition{name:string;description?:string;enabled:boolean;trigger:AutomationTrigger;conditions:ConditionGroup;actions:AutomationAction[];schedule?:AutomationSchedule|null;}
export interface AutomationExecution{id:string;automationId:string;status:"pending"|"running"|"completed"|"failed"|"skipped";triggerType:string;triggerData:Record<string,unknown>;startedAt?:string;completedAt?:string;error?:string;result?:{actions:import("./action").ActionResult[]};}
export interface AutomationPage{data:AutomationRule[];current_page:number;last_page:number;per_page:number;total:number}
export interface AutomationExecutionPage{data:AutomationExecution[];current_page:number;last_page:number;per_page:number;total:number}
