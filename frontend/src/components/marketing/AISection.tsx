import {
    Sparkles,
    Brain,
    TrendingUp
} from "lucide-react";


const insights = [

{
    icon: Brain,
    title:"AI Device Intelligence",
    description:
    "Automatically detect abnormal device behavior and operational risks."
},


{
    icon: TrendingUp,
    title:"Predictive Analytics",
    description:
    "Predict failures before they happen using historical telemetry data."
},


{
    icon: Sparkles,
    title:"Natural Language Assistant",
    description:
    "Ask questions about your IoT system using AI-powered conversations."
}

];



export default function AISection(){


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
rounded-2xl
border
border-cyan-400/20
bg-gradient-to-br
from-[#0B1628]
to-[#08101d]
p-8
"

>


<div

className="
grid
gap-10
md:grid-cols-2
"

>


{/* Left */}

<div>


<p

className="
flex
items-center
gap-2
text-sm
uppercase
tracking-widest
text-cyan-400
"

>

<Sparkles size={16}/>

AI Intelligence

</p>



<h2

className="
mt-4
text-4xl
font-bold
text-white
"

>

Turn IoT data into intelligent decisions

</h2>



<p

className="
mt-4
text-gray-400
"

>

Monitor thousands of devices,
identify problems,
and automate decisions using AI.

</p>



<div

className="
mt-6
rounded-xl
border
border-white/10
bg-black/20
p-5
"

>


<p

className="
text-sm
text-gray-400
"

>

AI Insight

</p>


<p

className="
mt-3
text-white
"

>

"Pump #102 shows abnormal vibration.
Estimated failure probability: 82%"

</p>


</div>



</div>




{/* Right */}

<div

className="
space-y-4
"

>


{

insights.map((item)=>(


<div

key={item.title}

className="
flex
gap-4
rounded-xl
border
border-white/10
bg-white/5
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


<item.icon size={20}/>


</div>



<div>


<h3

className="
font-semibold
text-white
"

>

{item.title}

</h3>


<p

className="
mt-1
text-sm
text-gray-400
"

>

{item.description}

</p>


</div>


</div>


))


}


</div>


</div>


</div>


</div>


</section>

);

}