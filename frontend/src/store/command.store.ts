import {

create

}

from "zustand";


import type {

DeviceCommand

}

from "../types/command";




interface CommandState {


commands:DeviceCommand[];


addCommand:

(command:DeviceCommand)=>void;


updateCommand:

(

id:string,

status:DeviceCommand["status"]

)=>void;


}





export const useCommandStore =

create<CommandState>((set)=>({


commands:[],



addCommand(command){


set(state=>({


commands:[

...state.commands,

command

]


}));


},





updateCommand(id,status){


set(state=>({


commands:

state.commands.map(command=>

command.id===id

?

{

...command,

status

}

:

command

)


}));


}



}));