import type {

WidgetDefinition

}

from "../../types/widget";




export const widgetRegistry:WidgetDefinition[]=[



{


type:"temperature",


name:"Temperature Gauge",


description:
"Displays live temperature telemetry.",


category:"monitoring",


icon:"🌡️"


},




{


type:"energy",


name:"Energy Monitor",


description:
"Tracks power consumption.",


category:"monitoring",


icon:"⚡"


},




{


type:"machine-status",


name:"Machine Status",


description:
"Shows machine health state.",


category:"industrial",


icon:"⚙️"


},




{


type:"alarm-panel",


name:"Alarm Panel",


description:
"Displays active incidents.",


category:"industrial",


icon:"🚨"


},




{


type:"ai-insight",


name:"AI Insight",


description:
"AI generated operational insights.",


category:"ai",


icon:"🤖"


},




{


type:"digital-twin",


name:"Digital Twin Viewer",


description:
"Visualize connected assets.",


category:"digital-twin",


icon:"🏭"


}



];