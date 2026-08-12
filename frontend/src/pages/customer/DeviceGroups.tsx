import {

useEffect,

useState

}

from "react";


import {

getGroups

}

from "../../services/device-group.service";


import GroupCard

from "../../components/device/groups/GroupCard";


import type {

DeviceGroup

}

from "../../types/device-group";





export default function DeviceGroups(){



const [groups,setGroups]

=

useState<DeviceGroup[]>([]);





useEffect(()=>{


getGroups()

.then(setGroups);


},[]);





return (

<div>


<h1

className="
text-3xl
font-bold
text-white
"

>

Device Groups

</h1>





<div

className="
mt-8
grid
gap-5
md:grid-cols-2
xl:grid-cols-3
"

>


{

groups.map(group=>(


<GroupCard

key={group.id}

group={group}

onOpen={()=>{}}

/>


))


}



</div>



</div>

);


}