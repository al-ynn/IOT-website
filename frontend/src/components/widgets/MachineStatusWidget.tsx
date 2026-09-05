interface Props {

status:string;

}



export default function MachineStatusWidget({

status

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


<p

className="
text-sm
text-gray-400
"

>

Machine Status

</p>



<div

className="
mt-4
flex
items-center
gap-3
"

>


<span

className="
h-3
w-3
rounded-full
bg-green-400
"

/>



<span className="text-white">

{status}

</span>


</div>


</div>

);


}