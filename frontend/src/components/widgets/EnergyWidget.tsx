interface Props {

value:number;

}



export default function EnergyWidget({

value

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

Energy Usage

</p>



<h2

className="
mt-4
text-3xl
font-bold
text-yellow-400
"

>

{value} kWh

</h2>



</div>

);


}