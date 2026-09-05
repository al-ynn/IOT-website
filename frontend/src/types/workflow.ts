export type WorkflowStepType =


    | "condition"


    | "action"


    | "delay"


    | "notification";





export interface WorkflowStep {


    id:string;


    type:WorkflowStepType;


    config:Record<string,unknown>;


    order:number;


}





export interface Workflow {


    id:string;


    name:string;


    description:string;


    enabled:boolean;


    steps:WorkflowStep[];


    createdAt:string;


}
