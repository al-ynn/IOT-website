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

getDevice

}

from "../../services/device.service";


import type {

Device

}

from "../../types/device";


import DeviceInfo

from "../../components/device/details/DeviceInfo";


import DeviceMetrics

from "../../components/device/details/DeviceMetrics";


import DeviceActions

from "../../components/device/details/DeviceActions";


import TelemetryPreview

from "../../components/device/telemetry/TelemetryPreview";

import HealthIndicator 
from "../../components/health/HealthIndicator";


import LastSeen
from "../../components/health/LastSeen";



export default function DeviceDetails(){



const {

id

}

=

useParams();



const [device,setDevice]

=

useState<Device>();





useEffect(()=>{


if(id){

getDevice(id)

.then(setDevice);

}


},[id]);





if(!device){


return (

<p className="text-white">

Loading device...

</p>

);


}





return (

<div

className="
space-y-8
"

>


<div>

<h1

className="
text-3xl
font-bold
text-white
"

>

{device.name}

</h1>



<p

className="
mt-2
text-gray-400
"

>

{device.type}

</p>

</div>





<DeviceMetrics

battery={device.battery}

signal="Excellent"

/>


<div

className="
grid
gap-4
md:grid-cols-2
"

>

<HealthIndicator

label="Battery"

value={

device.health?.batteryLevel ?? 0

}

/>



<HealthIndicator

label="Signal"

value={

device.health?.signalStrength ?? 0

}

/>



</div>



<LastSeen

time={device.health?.lastHeartbeat}

/>

<DeviceInfo

device={device}

/>




<DeviceActions/>





<TelemetryPreview/>




</div>



);


}
