import type {ComponentType} from "react";
export interface AppNavigationItem{label:string;path:string;icon:ComponentType<{size?:number;className?:string}>;permission?:string;feature?:string;end?:boolean}
