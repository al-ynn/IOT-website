import {

Factory,

Building2,

Zap,

Tractor,

Truck,

HeartPulse

}

from "lucide-react";



const solutions=[


{

title:"Smart Manufacturing",

description:
"Monitor machines, optimize production, and reduce downtime.",

icon:Factory

},


{

title:"Smart Buildings",

description:
"Manage energy, security, and building automation.",

icon:Building2

},


{

title:"Energy Management",

description:
"Track consumption and optimize resource usage.",

icon:Zap

},


{

title:"Smart Agriculture",

description:
"Monitor environmental conditions and farming systems.",

icon:Tractor

},


{

title:"Fleet Management",

description:
"Track vehicles and operational performance.",

icon:Truck

},


{

title:"Healthcare IoT",

description:
"Connect medical devices and patient monitoring systems.",

icon:HeartPulse

}


];



export default function Solutions(){


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


<div

className="
text-center
"

>


<h2

className="
text-4xl
font-bold
text-white
"

>

Built For Every Industry

</h2>



<p

className="
mt-4
text-gray-400
"

>

Flexible IoT infrastructure that adapts to any connected ecosystem.

</p>


</div>



<div

className="
mt-12
grid
gap-6
md:grid-cols-3
"

>


{

solutions.map((item)=>(


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