interface Props {


status:string;


}



export default function LifecycleBadge({

status

}:Props){



return (

<span

className="
rounded-full
bg-white/10
px-3
py-1
text-sm
text-white
"

>

{status}

</span>

);


}