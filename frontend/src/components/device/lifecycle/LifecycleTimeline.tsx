import type {

LifecycleEvent

}

from "../../../types/lifecycle";





export default function LifecycleTimeline({

events

}:{

events:LifecycleEvent[];

}){



return (

<div

className="
space-y-4
"

>


{

events.map(event=>(


<div

key={event.id}

className="
rounded-xl
border
border-white/10
bg-[#0B1628]
p-4
"

>


<p

className="
font-semibold
text-white
"

>

{event.status}

</p>




<p

className="
mt-2
text-gray-400
"

>

{event.description}

</p>




<p

className="
mt-2
text-xs
text-gray-500
"

>

{event.createdAt}

</p>



</div>


))


}



</div>

);


}