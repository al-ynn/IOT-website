import type {

DeviceCertificate

}

from "../../../types/device-security";




export default function CertificateCard({

certificate

}:{

certificate:DeviceCertificate;

}){



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


<h3

className="
text-white
font-semibold
"

>

{certificate.certificateName}

</h3>




<div

className="
mt-3
space-y-2
text-sm
text-gray-400
"

>


<p>

Issued:

{certificate.issuedDate}

</p>



<p>

Expires:

{certificate.expiryDate}

</p>



<p>

Status:

{certificate.status}

</p>



</div>


</div>

);


}