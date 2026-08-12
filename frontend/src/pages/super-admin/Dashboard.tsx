export default function SuperAdminDashboard(){


return (

<div>


<h1

className="
text-3xl
font-bold
text-red-400
"

>

System Control Center

</h1>



<div

className="
mt-8
space-y-4
"

>


<div

className="
rounded-xl
border
border-red-400/20
p-5
"

>

Database Status:
Healthy

</div>



<div

className="
rounded-xl
border
border-red-400/20
p-5
"

>

API Status:
Operational

</div>



<div

className="
rounded-xl
border
border-red-400/20
p-5
"

>

Active Services:
Running

</div>


</div>


</div>

);


}