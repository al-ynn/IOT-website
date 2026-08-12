interface Props {


label:string;


type?:string;


value:string;


onChange:(value:string)=>void;


}





export default function AuthInput({

label,

type="text",

value,

onChange

}:Props){



return (

<div

className="
space-y-2
"

>


<label

className="
text-sm
text-gray-400
"

>

{label}

</label>



<input

type={type}

value={value}

onChange={(e)=>

onChange(e.target.value)

}

className="
w-full
rounded-lg
bg-[#101F36]
p-3
text-white
outline-none
"

/>



</div>

);

}