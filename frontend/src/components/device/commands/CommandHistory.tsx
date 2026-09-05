import type {

DeviceCommand

}

from "../../../types/command";




export default function CommandHistory({

commands

}:{

commands:DeviceCommand[];

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

Command History

</h3>




<div

className="
mt-4
space-y-3
"

>


{

commands.map(command=>(


<div

key={command.id}

className="
rounded-lg
bg-white/5
p-3
"

>


<p className="text-white">

{command.command}

</p>



<p className="text-sm text-gray-400">

{command.status}

</p>


</div>


))


}


</div>


</div>

);


}