import {

useEffect,

useState

}

from "react";


import {

getWorkflows

}

from "../../services/workflow.service";


import type {

Workflow

}

from "../../types/workflow";


import WorkflowCard

from "../../engine/workflow/WorkflowCard";





export default function Workflows(){



const [workflows,setWorkflows]

=

useState<Workflow[]>([]);





useEffect(()=>{


getWorkflows()

.then(setWorkflows);


},[]);





return (

<div>


<h1

className="
text-3xl
font-bold
text-white
"

>

Workflows

</h1>




<div

className="
mt-8
grid
gap-5
md:grid-cols-2
"

>


{

workflows.map(workflow=>(


<WorkflowCard

key={workflow.id}

workflow={workflow}

/>


))


}



</div>



</div>

);

}
