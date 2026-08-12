import {

create

}

from "zustand";


import type {

Widget,

Dashboard

}

from "../types/dashboard";




interface DashboardState {


dashboard:Dashboard;



widgets:Widget[];



selectedWidget:string|null;



setDashboard:

(
dashboard:Dashboard
)=>void;



addWidget:

(
widget:Widget
)=>void;



selectWidget:

(
id:string|null
)=>void;



updateWidget:

(
id:string,
data:Partial<Widget>
)=>void;


updateLayout:

(
id:string,
layout:Widget["layout"]
)=>void;



loadWidgets:

(
widgets:Widget[]
)=>void;



clear:

()=>void;


}





export const useDashboardStore =

create<DashboardState>((set)=>({



dashboard:{


name:"Untitled Dashboard",


widgets:[]


},



widgets:[],



selectedWidget:null,





setDashboard(dashboard){


set({

dashboard

});


},






addWidget(widget){


set(state=>({


widgets:[

...state.widgets,

widget

]


}));


},






selectWidget(id){


set({

selectedWidget:id

});


},





updateWidget(id,data){


set(state=>({


widgets:

state.widgets.map(widget=>


widget.id===id

?

{

...widget,

...data

}

:

widget


)


}));


},



updateLayout(id,layout){


set(state=>({


widgets:

state.widgets.map(widget=>


widget.id===id

?

{
...widget,
layout
}

:

widget


)


}));


},





loadWidgets(widgets){


set({

widgets

});


},





clear(){


set({

widgets:[],

selectedWidget:null

});


}



}));
