import {

useParams

}

from "react-router-dom";


import TokenManager

from "../../components/device/security/TokenManager";


import SecurityStatus

from "../../components/device/security/SecurityStatus";




export default function DeviceSecurity(){



const {

id

}

=

useParams();





return (

<div

className="
space-y-8
"

>


<h1

className="
text-3xl
font-bold
text-white
"

>

Device Security

</h1>





<SecurityStatus

score={95}

/>




<TokenManager

deviceId={id!}

/>



</div>

);


}