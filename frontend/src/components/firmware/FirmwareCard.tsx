import type {

Firmware

}

from "../../types/firmware";




interface Props {


firmware:Firmware;


onUpdate:()=>void;


}




export default function FirmwareCard({

firmware,

onUpdate

}:Props){



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

{firmware.name}

</h3>




<p

className="
mt-2
text-gray-400
"

>

Version:

{firmware.version}

</p>




<p

className="
mt-2
text-sm
text-gray-400
"

>

{firmware.description}

</p>




<button

onClick={onUpdate}

className="
mt-5
rounded-lg
bg-primary
px-4
py-2
text-white
"

>

Deploy Update

</button>



</div>

);


}