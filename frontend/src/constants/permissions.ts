import type {

Permission

}

from "../types/permission";





export const permissions:Permission[]=[


{

id:"device.view",

name:"View Devices",

description:"Can view registered devices",

category:"device"

},


{

id:"device.create",

name:"Create Devices",

description:"Can register devices",

category:"device"

},


{

id:"device.update",

name:"Update Devices",

description:"Can modify device settings",

category:"device"

},


{

id:"device.delete",

name:"Delete Devices",

description:"Can remove devices",

category:"device"

},


{

id:"telemetry.view",

name:"View Telemetry",

description:"Can view sensor data",

category:"telemetry"

},


{

id:"telemetry.export",

name:"Export Telemetry",

description:"Can export sensor history",

category:"telemetry"

},


{

id:"automation.create",

name:"Create Automation",

description:"Can create automation rules",

category:"automation"

},


{

id:"report.generate",

name:"Generate Reports",

description:"Can generate reports",

category:"reports"

},

{
    id:"audit.view",

    name:"View Audit Logs",

    description:
        "Can view organization audit logs",

    category:"audit"

}


];