import CommandButton

from "./CommandButton";


import {

sendCommand

}

from "../../../services/command.service";




export default function CommandPanel({

deviceId

}:{

deviceId:string;

}){





async function execute(command:string){


await sendCommand({


deviceId,


command,


status:"pending",


createdAt:

new Date().toISOString()


});


}





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

Device Controls

</h3>



<div

className="
mt-5
flex
flex-wrap
gap-3
"

>


<CommandButton

label="Restart Device"

onClick={()=>execute("RESTART")}

/>



<CommandButton

label="Sync Device"

onClick={()=>execute("SYNC")}

/>



<CommandButton

label="Factory Reset"

onClick={()=>execute("RESET")}

/>



</div>


</div>

);


}