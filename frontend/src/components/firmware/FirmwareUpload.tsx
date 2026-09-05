import {

useState

}

from "react";


import {

uploadFirmware

}

from "../../services/firmware.service";





export default function FirmwareUpload(){



const [file,setFile]

=

useState<File>();





async function submit(){


if(!file)

return;




const data=

new FormData();



data.append(

"firmware",

file

);



await uploadFirmware(data);


alert(

"Firmware uploaded"

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


<input

type="file"

onChange={(e)=>

setFile(

e.target.files?.[0]

)

}

className="
text-white
"

/>




<button

onClick={submit}

className="
mt-4
rounded-lg
bg-primary
px-4
py-2
text-white
"

>

Upload Firmware

</button>



</div>

);


}