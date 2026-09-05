export default function DeviceActions(){



function sendCommand(command:string){


console.log(

"Command:",

command

);


}





return (

<div

className="
flex
flex-wrap
gap-3
"

>


<button

onClick={()=>sendCommand("restart")}

className="
rounded-lg
bg-yellow-500/20
px-4
py-2
text-yellow-400
"

>

Restart Device

</button>




<button

onClick={()=>sendCommand("update")}

className="
rounded-lg
bg-blue-500/20
px-4
py-2
text-blue-400
"

>

Update Firmware

</button>




<button

onClick={()=>sendCommand("logs")}

className="
rounded-lg
bg-white/10
px-4
py-2
text-white
"

>

View Logs

</button>



</div>

);


}