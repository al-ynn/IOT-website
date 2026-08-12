export default function AdminDashboard(){


return (

<div>


<h1

className="
text-3xl
font-bold
"

>

Platform Dashboard

</h1>



<div

className="
mt-8
grid
gap-4
md:grid-cols-4
"

>


{

[

"Organizations",

"Users",

"Devices",

"Revenue"

].map(item=>(


<div

key={item}

className="
rounded-xl
border
border-white/10
bg-white/5
p-5
"

>


<p

className="
text-gray-400
"

>

{item}

</p>


<h2

className="
mt-3
text-2xl
font-bold
"

>

12,540

</h2>


</div>


))

}


</div>


</div>

);


}