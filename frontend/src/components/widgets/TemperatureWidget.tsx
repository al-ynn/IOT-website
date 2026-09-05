interface Props {

value:number;

unit:string;

}



export default function TemperatureWidget({

value,

unit

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

Temperature

</p>



<div

className="
mt-4
text-4xl
font-bold
text-cyan-400
"

>

{value}

{unit}

</div>


</div>

);


}