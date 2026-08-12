import {Activity,BarChart3,Building2,CreditCard,FileText,History,LayoutDashboard,Radio,Settings,Workflow} from "lucide-react";import type {AppNavigationItem} from "./app-navigation";
export const customerNavigation:AppNavigationItem[]=[
 {label:"Dashboard",path:"/app/dashboard",icon:LayoutDashboard,end:true},
 {label:"Devices",path:"/app/devices",icon:Activity,permission:"device.view"},
 {label:"Telemetry",path:"/app/telemetry",icon:Radio,permission:"device.view"},
 {label:"Analytics",path:"/app/analytics",icon:BarChart3,permission:"analytics.view",feature:"analytics.advanced"},
 {label:"Reports",path:"/app/reports",icon:FileText},
 {label:"Automation",path:"/app/automations",icon:Workflow,permission:"automation.view",feature:"automation.basic"},
 {label:"Activity",path:"/app/activity",icon:History,permission:"automation.view",feature:"automation.basic"},
 {label:"Organization",path:"/app/organization",icon:Building2},
 {label:"Billing",path:"/app/billing",icon:CreditCard},
 {label:"Settings",path:"/app/settings",icon:Settings},
];
