import {

useState

}

from "react";



export default function AIGenerator(){



const [prompt,setPrompt]=useState("");



function generate(){


console.log(

"Generate dashboard:",

prompt

);


}





return (

<div

className="
rounded-xl
border
border-cyan-400/20
bg-[#0B1628]
p-5
"

>


<h3

className="
font-semibold
text-cyan-400
"

>

AI Dashboard Generator

</h3>




<textarea

value={prompt}

onChange={(e)=>

setPrompt(e.target.value)

}

placeholder="
Example:
Create a factory monitoring dashboard
"

className="
mt-4
h-32
w-full
rounded-lg
bg-white/5
p-3
text-white
"

/>




<button

onClick={generate}

className="
mt-4
rounded-lg
bg-primary
px-5
py-2
text-white
"

>

Generate

</button>


</div>

);


}