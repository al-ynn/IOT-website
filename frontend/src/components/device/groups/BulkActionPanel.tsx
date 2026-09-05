import {

bulkAction

}

from "../../../services/device-group.service";




export default function BulkActionPanel({

groupId

}:{

groupId:string;

}){



async function execute(action:string){


await bulkAction(

groupId,

action

);


}





return (

<div

className="
flex
gap-3
"

>


<button

onClick={()=>execute("RESTART")}

className="
rounded-lg
bg-yellow-500/20
px-4
py-2
text-yellow-400
"

>

Restart All

</button>




<button

onClick={()=>execute("SYNC")}

className="
rounded-lg
bg-blue-500/20
px-4
py-2
text-blue-400
"

>

Sync All

</button>




<button

onClick={()=>execute("UPDATE_FIRMWARE")}

className="
rounded-lg
bg-green-500/20
px-4
py-2
text-green-400
"

>

Update Firmware

</button>



</div>

);


}