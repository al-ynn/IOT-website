import {

Code2,

Database,

Webhook

}

from "lucide-react";



export default function DeveloperSection(){


const items=[

{

title:"REST API",

description:"Integrate your applications with powerful APIs.",

icon:Code2

},

{

title:"MQTT Support",

description:"Connect millions of IoT devices efficiently.",

icon:Database

},

{

title:"Webhooks",

description:"Trigger external workflows instantly.",

icon:Webhook

}

];



return (

<section

className="
py-24
"

>


<div

className="
mx-auto
max-w-7xl
px-6
lg:px-8
"

>


<h2

className="
text-center
text-4xl
font-bold
text-white
"

>

Built For Developers

</h2>



<div

className="
mt-12
grid
gap-6
md:grid-cols-3
"

>


{

items.map((item)=>(


<div

key={item.title}

className="
rounded-2xl
border
border-white/10
bg-[#0B1628]
p-6
"

>


<item.icon

className="
text-cyan-400
"

/>


<h3

className="
mt-5
text-xl
font-semibold
text-white
"

>

{item.title}

</h3>



<p

className="
mt-3
text-gray-400
"

>

{item.description}

</p>



</div>


))

}


</div>


</div>


</section>

);


}