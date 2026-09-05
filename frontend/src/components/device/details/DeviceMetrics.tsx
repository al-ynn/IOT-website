interface Props {


    battery?:number;


    signal?:string;


}



export default function DeviceMetrics({

    battery,

    signal

}:Props){



return (

<div

className="
grid
gap-4
md:grid-cols-2
"

>


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

Battery

</p>



<h2

className="
mt-3
text-3xl
font-bold
text-white
"

>

{battery ?? "--"}%

</h2>



</div>





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

Signal

</p>



<h2

className="
mt-3
text-3xl
font-bold
text-white
"

>

{signal ?? "Unknown"}

</h2>



</div>



</div>

);


}