export type ActionType =

    | "notification";





export interface AutomationAction {


    id?:string;


    type:ActionType;


    target:string;


    payload?:Record<string,unknown>;

    continueOnFailure?:boolean;


}





export interface ActionResult {


    actionId:string;


    success:boolean;


    message:string;


    executedAt:string;


}
