import {

executeWorkflowSteps

}

from "./workflow.executor";


import type {

Workflow

}

from "../../types/workflow";





export async function runWorkflow(

workflow:Workflow,

context:Record<string,unknown>

){



return await executeWorkflowSteps(

workflow.steps,

context

);


}
