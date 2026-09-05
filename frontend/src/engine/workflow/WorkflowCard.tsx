import type { Workflow } from "../../types/workflow";


export default function WorkflowCard({

workflow

}:{

workflow:Workflow;

}){


return (

<div className="rounded-xl border border-white/10 bg-[#0B1628] p-5">

<h3 className="font-semibold text-white">

{workflow.name}

</h3>

<p className="mt-2 text-sm text-gray-400">

{workflow.description}

</p>

<p className="mt-4 text-sm text-gray-400">

{workflow.steps.length} steps

</p>

</div>

);


}
