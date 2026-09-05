interface Props {


label:string;


value:string;


description?:string;


}



export default function MetricCard({

label,

value,

description

}:Props){


return (

<div

className="
rounded-xl
border
border-white/10
bg-[#101F36]
p-5
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



<h2

className="
mt-3
text-3xl
font-bold
text-white
"

>

{value}

</h2>



{

description &&

(

<p

className="
mt-2
text-xs
text-gray-500
"

>

{description}

</p>

)

}


</div>

);


}