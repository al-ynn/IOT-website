import type {
    ReactNode
}
from "react";


export default function SuperAdminLayout({

children

}:{

children:ReactNode;

}){


return (

<div

className="
min-h-screen
bg-black
text-white
"

>


<header

className="
border-b
border-red-500/30
px-6
py-4
"

>


<h1

className="
font-semibold
text-red-400
"

>

Super Admin Console

</h1>


</header>



<main

className="
p-6
"

>

{children}

</main>


</div>

);


}