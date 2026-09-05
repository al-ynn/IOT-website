interface Props {


score:number;


}



export default function SecurityStatus({

score

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


<p className="text-gray-400">

Security Score

</p>




<h2

className="
mt-3
text-4xl
font-bold
text-white
"

>

{score}/100

</h2>



</div>

);


}