const industries = [

    "Manufacturing",

    "Energy",

    "Smart Buildings",

    "Agriculture",

    "Healthcare",

    "Logistics"

];


export default function TrustedCompanies(){


return (

<section

className="
border-y
border-white/10
py-12
"

>


<div

className="
mx-auto
max-w-6xl
px-6
"

>


<div

className="
flex
flex-col
gap-8
md:flex-row
md:items-center
md:justify-between
"

>


<div>


<p

className="
text-sm
uppercase
tracking-wider
text-gray-500
"

>

Trusted Across Industries

</p>


<h3

className="
mt-2
text-xl
font-semibold
text-white
"

>

One platform.
Thousands of connected possibilities.

</h3>


</div>




<div

className="
grid
grid-cols-2
gap-3
sm:grid-cols-3
"

>


{

industries.map((item)=>(


<div

key={item}

className="
rounded-lg
border
border-white/10
bg-white/5
px-4
py-3
text-center
text-sm
text-gray-300
"

>

{item}

</div>


))

}


</div>



</div>


</div>


</section>

);


}
