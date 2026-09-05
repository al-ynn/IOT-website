import {

useParams

}

from "react-router-dom";


import CommandPanel

from "../../components/device/commands/CommandPanel";




export default function DeviceCommands(){



const {

id

}

=

useParams();





return (

<div>


<h1

className="
text-3xl
font-bold
text-white
"

>

Device Control

</h1>



<div

className="
mt-8
max-w-xl
"

>


<CommandPanel

deviceId={id!}

/>


</div>



</div>

);


}