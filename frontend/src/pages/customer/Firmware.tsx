import FirmwareUpload

from "../../components/firmware/FirmwareUpload";


import {

useEffect,

useState

}

from "react";


import {

getFirmware

}

from "../../services/firmware.service";


import type {

Firmware

}

from "../../types/firmware";


import FirmwareCard

from "../../components/firmware/FirmwareCard";





export default function Firmware(){



const [firmware,setFirmware]

=

useState<Firmware[]>([]);





useEffect(()=>{


getFirmware()

.then(setFirmware);


},[]);





return (

<div>


<h1

className="
text-3xl
font-bold
text-white
"

>

Firmware Management

</h1>




<div className="mt-8">

<FirmwareUpload/>

</div>





<div

className="
mt-8
grid
gap-5
md:grid-cols-2
"

>


{

firmware.map(item=>(


<FirmwareCard

key={item.id}

firmware={item}

onUpdate={()=>{}}

/>


))


}



</div>



</div>

);


}