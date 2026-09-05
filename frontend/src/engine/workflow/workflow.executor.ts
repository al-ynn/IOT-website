import type {

WorkflowStep

}

from "../../types/workflow";





export async function executeWorkflowSteps(

steps:WorkflowStep[],

context:Record<string,unknown>

){


void context;



const results=[];




for(const step of steps){



switch(step.type){



case "delay":


await new Promise(resolve =>

setTimeout(

resolve,

typeof step.config.duration==="number"

?

step.config.duration

:

0

)

);


break;





case "action":


console.log(

"Execute action",

step.config

);


break;





case "condition":


console.log(

"Check condition",

step.config

);


break;





case "notification":


console.log(

"Send notification",

step.config

);


break;



}





results.push({

step:step.id,

status:"completed"

});



}




return results;


}
