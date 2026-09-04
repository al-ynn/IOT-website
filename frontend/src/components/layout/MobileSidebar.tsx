import {useEffect,useRef} from "react";
import {X} from "lucide-react";
import type {ReactNode} from "react";

export default function MobileSidebar({open,onClose,children,label="Navigation drawer"}:{open:boolean;onClose():void;children:ReactNode;label?:string}){
 const previousFocus=useRef<HTMLElement|null>(null);
 const panel=useRef<HTMLDivElement|null>(null);
 useEffect(()=>{if(!open)return;previousFocus.current=document.activeElement instanceof HTMLElement?document.activeElement:null;const onKeyDown=(event:KeyboardEvent)=>{if(event.key==="Escape")onClose();if(event.key!=="Tab")return;const focusable=Array.from(panel.current?.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])')??[]);if(!focusable.length)return;const first=focusable[0],last=focusable[focusable.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}};const onResize=()=>{if(window.matchMedia("(min-width: 1024px)").matches)onClose()};document.addEventListener("keydown",onKeyDown);window.addEventListener("resize",onResize);requestAnimationFrame(()=>panel.current?.querySelector<HTMLElement>("button,a")?.focus());return()=>{document.removeEventListener("keydown",onKeyDown);window.removeEventListener("resize",onResize);previousFocus.current?.focus()}},[open,onClose]);
 if(!open)return null;
 return <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label={label}><button type="button" aria-label="Close navigation overlay" className="absolute inset-0 bg-[var(--ds-overlay)]" onClick={onClose}/><div ref={panel} className="relative h-full w-[min(17rem,88vw)] border-r border-[var(--ds-border)] shadow-2xl">{children}<button type="button" aria-label="Close navigation" onClick={onClose} className="absolute right-3 top-3 grid min-h-10 min-w-10 place-items-center rounded-[8px] text-[var(--ds-text-muted)] hover:bg-white/[.05] focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)]"><X size={18} aria-hidden="true"/></button></div></div>;
}
