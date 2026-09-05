import {
    ArrowRight,
    Play
}
from "lucide-react";


import {
    motion
}
from "framer-motion";

import {Link} from "react-router-dom";



export default function Hero(){


return (

<section

className="
relative
overflow-hidden
pt-40
pb-24
"

>


{/* Background Glow */}

<div

className="
absolute
left-1/2
top-20
h-[500px]
w-[500px]
-translate-x-1/2
rounded-full
bg-cyan-500/20
blur-[120px]
"

></div>




<div

className="
relative
mx-auto
max-w-7xl
px-6
"

>


<div

className="
mx-auto
max-w-4xl
text-center
"

>


<motion.div

initial={{
opacity:0,
y:20
}}

animate={{
opacity:1,
y:0
}}

transition={{
duration:0.7
}}

>


{/* Badge */}

<div

className="
mx-auto
mb-8
inline-flex
items-center
gap-2
rounded-full
border
border-cyan-400/20
bg-cyan-400/10
px-5
py-2
text-sm
text-cyan-300
"

>


<span

className="
h-2
w-2
rounded-full
bg-cyan-400
animate-pulse
"

/>


AI Powered IoT Platform


</div>





<h1

className="
text-5xl
font-bold
tracking-tight
text-white
md:text-7xl
"

>


Connect.

<br/>


Monitor.

<br/>


<span

className="
bg-gradient-to-r
from-cyan-400
to-blue-500
bg-clip-text
text-transparent
"

>

Automate Everything.

</span>


</h1>




<p

className="
mx-auto
mt-8
max-w-2xl
text-lg
leading-relaxed
text-gray-400
"

>


A complete IoT ecosystem for device management,
Device telemetry, automation, analytics,
and reproducible operational reports.


</p>





{/* Buttons */}

<div

className="
mt-10
flex
flex-col
justify-center
gap-4
sm:flex-row
"

>


<Link

to="/login"

className="
flex
items-center
justify-center
gap-2
rounded-xl
bg-primary
px-8
py-4
font-medium
transition
hover:scale-105
"

>

Start Building

<ArrowRight size={18}/>


</Link>





<Link

to="/login"

className="
flex
items-center
justify-center
gap-2
rounded-xl
border
border-white/10
bg-white/5
px-8
py-4
text-white
transition
hover:bg-white/10
"

>


<Play size={18}/>

View Platform


</Link>


</div>



</motion.div>


</div>





{/* Dashboard Preview */}

<motion.div

initial={{
opacity:0,
y:60
}}

animate={{
opacity:1,
y:0
}}

transition={{
duration:0.8,
delay:0.2
}}

className="
mx-auto
mt-20
max-w-5xl
"

>


<div

className="
rounded-3xl
border
border-white/10
bg-[#0B1628]
p-6
shadow-2xl
shadow-cyan-500/10
"

>



{/* Fake Browser Header */}

<div

className="
flex
items-center
gap-2
border-b
border-white/10
pb-5
"

>


<div className="
h-3
w-3
rounded-full
bg-red-400
"></div>


<div className="
h-3
w-3
rounded-full
bg-yellow-400
"></div>


<div className="
h-3
w-3
rounded-full
bg-green-400
"></div>



</div>





<div

className="
mt-6
grid
gap-5
md:grid-cols-3
"

>


<MetricCard

title="Devices Online"

value="4,826"

/>



<MetricCard

title="Critical Alerts"

value="03"

/>



<MetricCard

title="System Health"

value="98%"

/>


</div>




<div

className="
mt-6
h-48
rounded-2xl
bg-gradient-to-r
from-blue-500/20
via-cyan-500/20
to-transparent
"

>


<div

className="
flex
h-full
items-center
justify-center
text-gray-400
"

>

Telemetry Visualization

</div>


</div>




</div>


</motion.div>



</div>


</section>

);


}




function MetricCard({

title,

value

}:{

title:string;

value:string;

}){


return (

<div

className="
rounded-2xl
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

{title}

</p>


<h3

className="
mt-3
text-3xl
font-bold
text-white
"

>

{value}

</h3>


</div>

);


}
