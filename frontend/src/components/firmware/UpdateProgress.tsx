interface Props {


progress:number;


status:string;


}



export default function UpdateProgress({

progress,

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


<p className="text-white">

{status}

</p>




<div

className="
mt-4
h-3
rounded-full
bg-white/10
"

>


<div

style={{

width:`${progress}%`

}}

className="
h-full
rounded-full
bg-primary
"

/>


</div>



</div>

);


}