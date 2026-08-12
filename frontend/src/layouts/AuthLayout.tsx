import type {

ReactNode

}

from "react";




export default function AuthLayout({

children

}:{

children:ReactNode;

}){


return (

<div

className="
min-h-screen
flex
items-center
justify-center
bg-background
px-6
"

>


<div

className="
w-full
max-w-md
rounded-2xl
border
border-white/10
bg-[#0B1628]
p-8
"

>


{children}


</div>


</div>

);


}