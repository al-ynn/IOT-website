import {

rotateToken

}

from "../../../services/device-security.service";




export default function TokenManager({

deviceId

}:{

deviceId:string;

}){



async function rotate(){


await rotateToken(deviceId);


alert(

"Token rotated"

);


}





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


<h3 className="text-white">

Access Tokens

</h3>




<button

onClick={rotate}

className="
mt-4
rounded-lg
bg-primary
px-4
py-2
text-white
"

>

Rotate Token

</button>



</div>

);


}