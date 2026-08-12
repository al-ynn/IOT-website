import {

create

}

from "zustand";





interface AppStore {


    sidebarOpen:
        boolean;


    toggleSidebar:
        ()=>void;



}





export const useAppStore =

create<AppStore>((set)=>({



    sidebarOpen:
        true,



    toggleSidebar(){

        set(
            state=>({

                sidebarOpen:
                !state.sidebarOpen


            })
        );


    }



}));