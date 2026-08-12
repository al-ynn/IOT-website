import {

widgetRegistry

}

from "./WidgetRegistry";




export default function WidgetLibrary(){



return (

<div

className="
grid
gap-4
md:grid-cols-2
"

>


{

widgetRegistry.map(widget=>(


<div

key={widget.type}

className="
cursor-pointer
rounded-xl
border
border-white/10
bg-[#0B1628]
p-4
hover:bg-white/5
"

>


<div className="text-2xl">

{widget.icon}

</div>



<h3

className="
mt-2
font-medium
text-white
"

>

{widget.name}

</h3>



<p

className="
mt-1
text-sm
text-gray-400
"

>

{widget.description}

</p>



</div>


))


}


</div>

);


}