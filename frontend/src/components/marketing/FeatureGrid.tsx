import {

Activity,

Cpu,

Database,

Workflow

}

from "lucide-react";



const features=[


{

title:"Device Management",

description:
"Register, monitor, and control thousands of connected devices.",

icon:Cpu

},


{

title:"Device Telemetry",

description:
"Collect and inspect authenticated Device measurements.",

icon:Activity

},


{

title:"Data Platform",

description:
"Store, analyze, and transform IoT information.",

icon:Database

},


{

title:"Automation Engine",

description:
"Create intelligent workflows and device actions.",

icon:Workflow

}


];



export default function FeatureGrid(){


return (

<section

className="
py-20
"

>


<div

className="
mx-auto
max-w-6xl
px-6
"

>


<div

className="
max-w-3xl
"

>


<p

className="
text-sm
uppercase
tracking-widest
text-cyan-400
"

>

Platform Capabilities

</p>



<h2

className="
mt-3
text-4xl
font-bold
text-white
"

>

Everything needed to operate IoT at scale

</h2>



<p

className="
mt-4
text-gray-400
"

>

Manage devices, process data, automate operations,
and gain intelligence from every connected system.

</p>


</div>




<div

className="
mt-10
grid
gap-4
md:grid-cols-2
"

>


{

features.map((feature)=>(


<div

key={feature.title}

className="
flex
gap-4
rounded-xl
border
border-white/10
bg-[#0B1628]
p-5
"

>


<div

className="
rounded-lg
bg-cyan-400/10
p-3
text-cyan-400
"

>


<feature.icon size={22}/>


</div>



<div>


<h3

className="
font-semibold
text-white
"

>

{feature.title}

</h3>


<p

className="
mt-2
text-sm
text-gray-400
"

>

{feature.description}

</p>


</div>


</div>


))

}


</div>


</div>


</section>

);


}
