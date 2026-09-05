export default function LastSeen({

time

}:{

time?:string;

}){



return (

<p

className="
text-sm
text-gray-400
"

>

Last seen:

{" "}

{time ?? "Unknown"}

</p>

);


}