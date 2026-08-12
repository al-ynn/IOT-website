import {

useState

}

from "react";

import type { ReportSchedule, ScheduleFrequency } from "../../types/report-schedule";





interface Props {


onSave:(data:Pick<ReportSchedule,"frequency"|"time">)=>void;


}





export default function ScheduleForm({

onSave

}:Props){



const [frequency,setFrequency]

=

useState<ScheduleFrequency>("weekly");



const [time,setTime]

=

useState("08:00");





return (

<div

className="
space-y-4
"

>



<select

value={frequency}

onChange={(e)=>

setFrequency(e.target.value as ScheduleFrequency)

}

className="
rounded-lg
bg-[#0B1628]
p-3
text-white
"

>


<option value="daily">

Daily

</option>



<option value="weekly">

Weekly

</option>



<option value="monthly">

Monthly

</option>



</select>





<input

type="time"

value={time}

onChange={(e)=>

setTime(e.target.value)

}

className="
rounded-lg
bg-[#0B1628]
p-3
text-white
"

/>





<button

onClick={()=>onSave({

frequency,

time

})}

className="
rounded-lg
bg-primary
px-5
py-3
text-white
"

>

Save Schedule

</button>



</div>

);

}
