import {

useEffect

}

from "react";


import {

echo

}

from "../services/websocket.service";


import {

useDeviceStore

}

from "../store/device.store";

import type { Device } from "../types/device";




export default function useDeviceHealth(enabled=true){



const updateDeviceHealth =

useDeviceStore(

state=>state.updateDeviceHealth

);





useEffect(()=>{

if(!enabled||!echo)return;

const connection=echo;


connection

.channel("device-health")

.listen(

".device.health.updated",

(event:{ deviceId:string; health:Device["health"] })=>{


updateDeviceHealth(

event.deviceId,

event.health

);


}

);



return ()=>{


connection.leaveChannel(

"device-health"

);


};



},[enabled,updateDeviceHealth]);



}
