import {

useState

}

from "react";


import DeviceQRCode

from "../../components/device/onboarding/DeviceQRCode";


import {

generateDeviceClaim

}

from "../../services/onboarding.service";




export default function DeviceProvisioning(){



const [qr,setQr]=useState("");





async function generate(){


const result =

await generateDeviceClaim(

"device-id"

);



setQr(

JSON.stringify(result)

);


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

Device Provisioning

</h1>



<button

onClick={generate}

className="
mt-5
rounded-lg
bg-primary
px-5
py-3
text-white
"

>

Generate QR

</button>





{

qr && (

<div className="mt-8">


<DeviceQRCode

value={qr}

/>


</div>

)

}



</div>

);


}