import {

dashboardTemplates

}

from "../../data/dashboardTemplates";

import type { DashboardTemplate } from "../../data/dashboardTemplates";


import TemplateCard

from "./TemplateCard";




export default function TemplateSelector(){



function select(template:DashboardTemplate){


console.log(

"Selected template",

template

);


}




return (

<div

className="
grid
gap-5
md:grid-cols-2
"

>


{

dashboardTemplates.map(template=>(


<TemplateCard

key={template.id}

template={template}

onSelect={()=>select(template)}

/>


))


}


</div>

);


}
