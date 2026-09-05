import {

useState

}

from "react";


import {

claimDevice

}

from "../../services/onboarding.service";




export default function ClaimDevice(){



const [token,setToken]=useState("");





async function submit(){


const result=

await claimDevice(token);


console.log(result);


}





return (

<div>


<h1

className="
text-3xl
font-bold
text-white
"

>

Claim Device

</h1>




<input

value={token}

onChange={(e)=>

setToken(e.target.value)

}

placeholder="Claim Token"

className="
mt-5
w-full
rounded-lg
bg-white/5
p-3
text-white
"

/>



<button

onClick={submit}

className="
mt-4
rounded-lg
bg-primary
px-5
py-3
text-white
"

>

Claim Device

</button>



</div>

);


}