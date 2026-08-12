import {
create
}
from "zustand";


import type {
Telemetry
}
from "../types/telemetry";



interface TelemetryState {


data:Telemetry[];


setTelemetry:

(data:Telemetry)=>void;



}




export const useTelemetryStore=create<TelemetryState>(

(set)=>({


data:[],



setTelemetry(data){


set(state=>({


data:[

...state.data.filter(

item=>

item.deviceId!==data.deviceId

||

item.key!==data.key

),

data

]


}));


}



})

);