import Container from "../layout/Container";
import {Link} from "react-router-dom";


export default function CTA(){


return (

<section

className="
py-24
"

>


<Container>


<div

className="
rounded-3xl
border
border-cyan-400/20
bg-gradient-to-br
from-blue-600/20
to-cyan-500/10
p-12
text-center
"

>


<h2

className="
text-4xl
font-bold
text-white
"

>

Ready To Build The Future?

</h2>



<p

className="
mx-auto
mt-4
max-w-xl
text-gray-300
"

>

Connect your devices,
analyze your data,
and automate your world.

</p>



<Link

to="/login"

className="
mt-8
rounded-xl
bg-blue-600
px-8
py-4
font-semibold
text-white
hover:bg-blue-500
hover:scale-105
transition-all
duration-300
"

>

Start Building

</Link>


</div>


</Container>


</section>

);


}
