import {
Code2,
Webhook,
Database,
Radio
}
from "lucide-react";


const developerFeatures=[


{
icon:Radio,
title:"MQTT Ready",
description:
"Connect any IoT hardware using industry standard protocols."
},


{
icon:Code2,
title:"Developer APIs",
description:
"Build custom applications using powerful REST APIs."
},


{
icon:Webhook,
title:"Webhooks & Events",
description:
"Send supported signed events to your existing systems."
},


{
icon:Database,
title:"Data Access",
description:
"Query telemetry and device history securely."
}


];


export default function DeveloperPlatform(){


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
grid
gap-10
md:grid-cols-2
"

>


<div>


<p

className="
text-sm
uppercase
tracking-widest
text-cyan-400
"

>

Developer Platform

</p>



<h2

className="
mt-3
text-4xl
font-bold
text-white
"

>

Build anything on top of your IoT infrastructure

</h2>



<p

className="
mt-4
text-gray-400
"

>

Flexible APIs,
protocol support,
and integrations designed for developers.

</p>


</div>



<div

className="
grid
gap-4
sm:grid-cols-2
"

>


{

developerFeatures.map((feature)=>(


<div

key={feature.title}

className="
rounded-xl
border
border-white/10
bg-[#0B1628]
p-5
"

>


<feature.icon

className="
text-cyan-400
"

/>



<h3

className="
mt-3
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


))


}


</div>


</div>


</div>


</section>

);


}
