import type {LucideIcon} from "lucide-react";import {cn} from "../../utils/cn";
export type IconSize="small"|"default"|"medium"|"large";const sizes={small:14,default:16,medium:18,large:20};
export function Icon({icon:Glyph,size="default",label,className}:{icon:LucideIcon;size?:IconSize;label?:string;className?:string}){return <Glyph size={sizes[size]} aria-hidden={label?undefined:true} aria-label={label} role={label?"img":undefined} className={cn("shrink-0",className)}/>;}
