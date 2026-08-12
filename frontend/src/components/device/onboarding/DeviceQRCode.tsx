import {

QRCodeSVG

}

from "qrcode.react";



interface Props {


value:string;


}



export default function DeviceQRCode({

value

}:Props){



return (

<div

className="
rounded-xl
border
border-white/10
bg-white
p-5
"

>


<QRCodeSVG

value={value}

size={200}

/>



</div>

);


}