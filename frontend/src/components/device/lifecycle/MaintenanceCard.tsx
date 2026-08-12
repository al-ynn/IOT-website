import type {

MaintenanceRecord

}

from "../../../types/lifecycle";





export default function MaintenanceCard({

record

}:{

record:MaintenanceRecord;

}){



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


<h3 className="text-white">

{record.title}

</h3>



<p

className="
mt-2
text-gray-400
"

>

{record.description}

</p>



<p

className="
mt-2
text-sm
text-gray-500
"

>

Technician:

{record.technician}

</p>


</div>

);


}