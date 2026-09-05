import ResourceAttribution from "../../collaboration/ResourceAttribution";
import type {

Device

}

from "../../../types/device";




export default function DeviceInfo({

device

}:{

device:Device;

}){



return (

<div

className="
rounded-xl
border
border-white/10
bg-[#0B1628]
p-5
"

>


<h3

className="
font-semibold
text-white
"

>

Device Information

</h3>




<div

className="
mt-4
space-y-3
text-sm
text-gray-300
"

>


<p>

Serial Number:

{device.serialNumber}

</p>



<p>

Protocol:

{device.protocol}

</p>



<p>

Firmware:

{device.firmwareVersion ?? "Unknown"}

</p>



<p>

Location:

{device.location?.name ?? "Unassigned"}

</p>



</div>

{device.id&&<ResourceAttribution resourceType="device" resourceId={device.id}/>}

</div>

);


}