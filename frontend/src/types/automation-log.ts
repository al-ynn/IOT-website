import type { ActionResult } from "./action";

export type ExecutionStatus =


    | "success"


    | "failed"


    | "running";





export interface AutomationLog {


    id:string;


    automationId:string;


    automationName:string;


    status:ExecutionStatus;


    triggerData:Record<string,unknown>;


    actions:ActionResult[];


    error?:string;


    executedAt:string;


    duration?:number;


}
