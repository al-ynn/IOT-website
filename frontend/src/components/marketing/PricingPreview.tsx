import {Link} from "react-router-dom";

const plans = [

{
name:"Starter",

description:
"For developers and small IoT projects.",

features:[

"Device Management",

"Basic Telemetry",

"Community Support"

]

},


{
name:"Professional",

description:
"For growing IoT operations.",

features:[

"Advanced Dashboards",

"Automation Rules",

"Analytics",

"API Access"

],

popular:true

},


{
name:"Enterprise",

description:
"For large-scale organizations.",

features:[

"Unlimited Devices",

"Advanced Security",

"Dedicated Support"

]

}


];



export default function PricingPreview(){


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

Flexible plans for every IoT journey

</h2>



<p

className="
mt-4
text-gray-400
"

>

Start small and scale when your connected ecosystem grows.

</p>


</div>




<div

className="
mt-10
grid
gap-5
md:grid-cols-3
"

>


{

plans.map((plan)=>(


<div

key={plan.name}

className={`

rounded-xl

border

border-white/10

bg-[#0B1628]

p-6

${

plan.popular

?

"ring-1 ring-cyan-400"

:

""

}

`}

>


<h3

className="
text-xl
font-semibold
text-white
"

>

{plan.name}

</h3>



<p

className="
mt-3
text-sm
text-gray-400
"

>

{plan.description}

</p>




<ul

className="
mt-6
space-y-3
text-sm
text-gray-300
"

>


{

plan.features.map((feature)=>(


<li key={feature}>

✓ {feature}

</li>


))

}


</ul>


<Link

to="/pricing"

className="
mt-8
w-full
rounded-lg
bg-white/10
py-3
text-sm
font-medium
text-white
hover:bg-white/20
"

>

Learn More

</Link>



</div>


))


}


</div>


</div>


</section>

);


}
