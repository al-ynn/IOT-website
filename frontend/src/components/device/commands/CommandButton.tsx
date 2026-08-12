interface Props {


label:string;


onClick:()=>void;


}



export default function CommandButton({

label,

onClick

}:Props){



return (

<button

onClick={onClick}

className="
rounded-lg
bg-primary
px-4
py-2
text-white
hover:bg-blue-500
"

>

{label}

</button>

);


}