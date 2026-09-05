import {
    ShieldCheck,
    LockKeyhole,
    FileSearch,
    Users
}
from "lucide-react";



const securityFeatures = [

    {
        icon: ShieldCheck,
        title: "Operational Security",
        description:
            "Protect connected systems with secure infrastructure and best practices."
    },


    {
        icon: LockKeyhole,
        title: "Data Encryption",
        description:
            "Secure device communication and sensitive platform data."
    },


    {
        icon: Users,
        title: "Role-Based Access",
        description:
            "Control permissions across organizations, teams, and users."
    },


    {
        icon: FileSearch,
        title: "Audit Logs",
        description:
            "Track every important action for compliance and visibility."
    }

];



export default function SecuritySection(){


return (

<section

className="
py-20
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
max-w-3xl
"

>


<p

className="
text-sm
uppercase
tracking-widest
text-cyan-400
"

>

Operations Ready

</p>



<h2

className="
mt-3
text-4xl
font-bold
text-white
"

>

Built for secure IoT operations at scale

</h2>



<p

className="
mt-4
text-gray-400
"

>

Manage users, devices, and data with organization-grade
security controls.

</p>


</div>





<div

className="
mt-10
grid
gap-4
md:grid-cols-2
"

>


{

securityFeatures.map((item)=>(


<div

key={item.title}

className="
flex
gap-4
rounded-xl
border
border-white/10
bg-[#0B1628]
p-5
"

>


<div

className="
rounded-lg
bg-cyan-400/10
p-3
text-cyan-400
"

>

<item.icon size={22}/>

</div>




<div>


<h3

className="
font-semibold
text-white
"

>

{item.title}

</h3>


<p

className="
mt-2
text-sm
text-gray-400
"

>

{item.description}

</p>


</div>


</div>


))

}


</div>


</div>


</section>

);


}
