const steps=[

"Connect Devices",

"Collect Telemetry",

"Automate Actions",

"Analyze Intelligence"

];


export default function HowItWorks(){


return (

<section

className="
border-y
border-white/10
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


<h2

className="
text-3xl
font-bold
text-white
"

>

From Device To Intelligence

</h2>



<div

className="
mt-8
grid
gap-4
md:grid-cols-4
"

>


{

steps.map((step,index)=>(


<div

key={step}

className="
rounded-xl
border
border-white/10
bg-white/5
p-5
"

>


<span

className="
text-sm
text-cyan-400
"

>

0{index+1}

</span>


<h3

className="
mt-3
font-medium
text-white
"

>

{step}

</h3>


</div>


))

}


</div>


</div>


</section>

);


}