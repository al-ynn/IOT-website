import type {

DeviceGroup

}

from "../../../types/device-group";




interface Props {


group:DeviceGroup;


onOpen:()=>void;


}





export default function GroupCard({

group,

onOpen

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

{group.name}

</h3>




<p

className="
mt-2
text-gray-400
"

>

{group.deviceCount}

devices

</p>




<div

className="
mt-3
flex
gap-2
"

>

{

group.tags.map(tag=>(


<span

key={tag}

className="
rounded-full
bg-white/10
px-3
py-1
text-xs
text-gray-300
"

>

{tag}

</span>


))

}


</div>




<button

onClick={onOpen}

className="
mt-5
rounded-lg
bg-primary
px-4
py-2
text-white
"

>

Open Group

</button>



</div>

);


}