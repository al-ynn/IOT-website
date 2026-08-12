import {
useEffect
}

from "react";


import {
echo
}

from "../services/websocket.service";


import {
useTelemetryStore
}

from "../store/telemetry.store";

import type { Telemetry } from "../types/telemetry";




export default function useTelemetry(enabled=true){


const setTelemetry =

useTelemetryStore(

state=>state.setTelemetry

);



useEffect(()=>{

if(!enabled||!echo)return;

const connection=echo;


connection
.channel("telemetry")
.listen(
".telemetry.updated",

(event:{ telemetry:{
device_id:string;
key:string;
value:Telemetry["value"];
unit?:string;
} })=>{


setTelemetry({

id:
crypto.randomUUID(),


deviceId:
event.telemetry.device_id,


key:
event.telemetry.key,


value:
event.telemetry.value,


unit:
event.telemetry.unit,


timestamp:
new Date().toISOString()

});


}


);



return ()=>{


connection.leaveChannel(
"telemetry"
);


};


},[enabled,setTelemetry]);



}
