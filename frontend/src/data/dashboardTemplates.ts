import type {
Widget
}
from "../types/dashboard";



export interface DashboardTemplate {


id:string;


name:string;


description:string;


category:string;


widgets:Widget[];


}




export const dashboardTemplates:DashboardTemplate[]=[



{


id:"factory",


name:"Factory Monitoring",


description:
"Industrial machine monitoring dashboard.",


category:"Industrial",



widgets:[


{

id:"temperature-1",

type:"temperature",

settings:{

title:"Machine Temperature"

},


layout:{

x:0,

y:0,

w:4,

h:3

}

},



{

id:"machine-status-1",

type:"machine-status",

settings:{

title:"Machine Health"

},


layout:{

x:4,

y:0,

w:4,

h:3

}

}


]


},






{


id:"energy",


name:"Energy Management",


description:
"Monitor energy consumption.",


category:"Energy",



widgets:[


{

id:"energy-1",

type:"energy",

settings:{

title:"Power Usage"

},


layout:{

x:0,

y:0,

w:4,

h:3

}

}


]


}



];