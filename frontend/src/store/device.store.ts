import {

create

}

from "zustand";


import type {

Device

}

from "../types/device";




interface DeviceState {


devices:Device[];



updateDeviceHealth:

(

id:string,

health:Device["health"]

)=>void;



}





export const useDeviceStore =

create<DeviceState>((set)=>({



devices:[],





updateDeviceHealth(

id,

health

){



set(state=>({



devices:

state.devices.map(device=>



device.id===id

?

{

...device,

health

}

:

device



)



}));



}



}));
