import {

create

}

from "zustand";


import type {

Widget,

Dashboard

}

from "../types/dashboard";

const layoutsOverlap = (a: Widget["layout"], b: Widget["layout"]) =>
    a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;

function placeWithoutOverlap(layout: Widget["layout"], occupied: Widget["layout"][]) {
    const width = Math.min(12, Math.max(1, layout.w));
    const next = { ...layout, x: Math.max(0, Math.min(12 - width, layout.x)), y: Math.max(0, layout.y), w: width };
    while (occupied.some(other => layoutsOverlap(next, other))) next.y += 1;
    return next;
}




interface DashboardState {


dashboard:Dashboard;



widgets:Widget[];



selectedWidget:string|null;

timeRange:"1h"|"6h"|"24h"|"7d"|"30d";

setTimeRange:(range:"1h"|"6h"|"24h"|"7d"|"30d")=>void;



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

updateLayouts:(layouts:Array<{id:string;layout:Widget["layout"]}>)=>void;

removeWidget:(id:string)=>void;



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

timeRange:"24h",

setTimeRange(timeRange){set({timeRange});},





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
set(state=>{
const widgets=state.widgets.map(widget=>widget.id===id?{...widget,...data}:widget);
const changed=widgets.find(widget=>widget.id===id);
if(changed?.type!=="label"||data.settings?.fontSize===undefined)return{widgets};
const fontSize=Math.max(12,Math.min(64,data.settings.fontSize));
const desired={...changed.layout,w:Math.max(changed.layout.w,Math.ceil(fontSize/16)),h:Math.max(changed.layout.h,1+Math.ceil(fontSize/32))};
const occupied:Widget["layout"][]=[];
const resolved=new Map<string,Widget["layout"]>();
const labelLayout=placeWithoutOverlap(desired,occupied);
occupied.push(labelLayout);resolved.set(id,labelLayout);
for(const widget of [...widgets].filter(item=>item.id!==id).sort((a,b)=>a.layout.y-b.layout.y||a.layout.x-b.layout.x)){
const next=placeWithoutOverlap(widget.layout,occupied);
occupied.push(next);resolved.set(widget.id,next);
}
return{widgets:widgets.map(widget=>({...widget,layout:resolved.get(widget.id)??widget.layout}))};
});


},



updateLayout(id,layout){
set(state=>{
const occupied:Widget["layout"][]=[];
const resolved=new Map<string,Widget["layout"]>();
const moving=state.widgets.find(widget=>widget.id===id);
if(!moving)return state;
const movedLayout=placeWithoutOverlap(layout,occupied);
occupied.push(movedLayout);
resolved.set(id,movedLayout);
for(const widget of [...state.widgets].filter(item=>item.id!==id).sort((a,b)=>a.layout.y-b.layout.y||a.layout.x-b.layout.x)){
const next=placeWithoutOverlap(widget.layout,occupied);
occupied.push(next);
resolved.set(widget.id,next);
}
return{widgets:state.widgets.map(widget=>({...widget,layout:resolved.get(widget.id)??widget.layout}))};
});


},

updateLayouts(layouts){
set(state=>{
const incoming=new Map(layouts.map(item=>[item.id,item.layout]));
const occupied:Widget["layout"][]=[];
const resolved=new Map<string,Widget["layout"]>();
for(const widget of [...state.widgets].sort((a,b)=>{
const first=incoming.get(a.id)??a.layout;
const second=incoming.get(b.id)??b.layout;
return first.y-second.y||first.x-second.x;
})){
const layout=placeWithoutOverlap(incoming.get(widget.id)??widget.layout,occupied);
occupied.push(layout);
resolved.set(widget.id,layout);
}
return{widgets:state.widgets.map(widget=>({...widget,layout:resolved.get(widget.id)??widget.layout}))};
});
},

removeWidget(id){set(state=>({widgets:state.widgets.filter(widget=>widget.id!==id),selectedWidget:state.selectedWidget===id?null:state.selectedWidget}));},





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
