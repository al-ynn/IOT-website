import type {

DashboardTemplate

}

from "../../data/dashboardTemplates";



interface Props {


template:DashboardTemplate;


onSelect:()=>void;


}



export default function TemplateCard({

template,

onSelect

}:Props){



return (

<button

onClick={onSelect}

className="
rounded-xl
border
border-white/10
bg-[#0B1628]
p-5
text-left
hover:bg-white/5
"

>


<h3

className="
font-semibold
text-white
"

>

{template.name}

</h3>



<p

className="
mt-2
text-sm
text-gray-400
"

>

{template.description}

</p>



<span

className="
mt-4
inline-block
text-xs
text-cyan-400
"

>

{template.category}

</span>


</button>

);


}