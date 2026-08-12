interface Props {


value:number;


label:string;


}



export default function HealthIndicator({

value,

label

}:Props){



return (

<div

className="
rounded-xl
border
border-white/10
bg-[#0B1628]
p-4
"

>


<p

className="
text-sm
text-gray-400
"

>

{label}

</p>




<p

className="
mt-2
text-2xl
font-bold
text-white
"

>

{value}%

</p>



</div>

);


}