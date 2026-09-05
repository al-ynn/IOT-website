import {

useEffect,

useState

}

from "react";


import {

useParams

}

from "react-router-dom";


import {

getLifecycleHistory

}

from "../../services/lifecycle.service";


import LifecycleTimeline

from "../../components/device/lifecycle/LifecycleTimeline";


import type {

LifecycleEvent

}

from "../../types/lifecycle";





export default function DeviceLifecycle(){



const {

id

}

=

useParams();



const [events,setEvents]

=

useState<LifecycleEvent[]>([]);





useEffect(()=>{


if(id){


getLifecycleHistory(id)

.then(setEvents);


}


},[id]);





return (

<div>


<h1

className="
text-3xl
font-bold
text-white
"

>

Device Lifecycle

</h1>




<div className="mt-8">


<LifecycleTimeline

events={events}

/>


</div>



</div>

);


}