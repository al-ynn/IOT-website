import {
    Activity,
    AlertTriangle,
    Cpu,
    Wifi
}
from "lucide-react";


export default function DashboardPreview(){


return (

<section

className="
py-16
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
overflow-hidden
rounded-2xl
border
border-white/10
bg-[#0B1628]
shadow-xl
"

>


{/* Header */}

<div

className="
flex
items-center
justify-between
border-b
border-white/10
px-5
py-4
"

>


<div>


<p

className="
text-sm
text-gray-400
"

>

Operations Center

</p>


<h3

className="
font-semibold
text-white
"

>

IoT Fleet Dashboard

</h3>


</div>


<div

className="
flex
items-center
gap-2
text-xs
text-green-400
"

>

<span
className="
h-2
w-2
rounded-full
bg-green-400
"
/>

Systems Online

</div>


</div>





{/* Metrics */}

<div

className="
grid
gap-4
p-5
md:grid-cols-4
"

>


<Metric

icon={<Cpu size={18}/>}

label="Devices"

value="4,982"

/>



<Metric

icon={<Wifi size={18}/>}

label="Online"

value="4,826"

/>



<Metric

icon={<AlertTriangle size={18}/>}

label="Alerts"

value="03"

/>



<Metric

icon={<Activity size={18}/>}

label="Health"

value="98%"

/>


</div>






{/* Main Area */}

<div

className="
grid
gap-4
px-5
pb-5
md:grid-cols-3
"

>


<div

className="
md:col-span-2
rounded-xl
border
border-white/10
bg-white/5
p-5
"

>


<p

className="
text-sm
text-gray-400
"

>

Telemetry Analytics

</p>



<div

className="
mt-6
flex
h-32
items-end
gap-2
"

>


{

[40,60,45,80,70,95,65]

.map((height,index)=>(


<div

key={index}

style={{

height:`${height}%`

}}

className="
flex-1
rounded-t
bg-cyan-400/70
"

/>


))

}


</div>


</div>





<div

className="
rounded-xl
border
border-white/10
bg-white/5
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
mt-4
text-sm
text-white
"

>

Pump vibration anomaly detected.

</p>


<span

className="
mt-3
inline-block
rounded-full
bg-red-400/10
px-3
py-1
text-xs
text-red-400
"

>

Needs Review

</span>


</div>



</div>



</div>


</div>


</section>

);


}



function Metric({

icon,

label,

value

}:{

icon:React.ReactNode;

label:string;

value:string;

}){


return (

<div

className="
rounded-xl
border
border-white/10
bg-white/5
p-4
"

>


<div

className="
flex
items-center
gap-2
text-cyan-400
"

>

{icon}

<span
className="
text-xs
text-gray-400
"
>

{label}

</span>


</div>



<p

className="
mt-3
text-2xl
font-semibold
text-white
"

>

{value}

</p>


</div>

);


}