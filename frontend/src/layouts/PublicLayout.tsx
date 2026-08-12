import {useState,type ReactNode} from "react";
import {Link,NavLink,Outlet} from "react-router-dom";
import {Menu,X} from "lucide-react";
import {Button} from "../components/ui";
import {publicFooterNavigation,publicNavigation} from "../navigation/public-navigation";

const navClass=({isActive}:{isActive:boolean})=>`rounded-[8px] px-3 py-2 text-sm transition ${isActive?"bg-white/[.06] text-[var(--ds-text)]":"text-[var(--ds-text-muted)] hover:bg-white/[.04] hover:text-[var(--ds-text)]"}`;
export default function PublicLayout({children}:{children?:ReactNode}){
 const [open,setOpen]=useState(false);
 return <div className="min-h-screen overflow-x-hidden bg-[var(--ds-bg)] text-[var(--ds-text)]">
  <header className="sticky top-0 z-40 border-b border-[var(--ds-border-subtle)] bg-[color:var(--ds-surface)]/95 backdrop-blur">
   <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
    <Link to="/" className="flex items-center gap-2.5 text-sm font-bold" aria-label="IoT Platform home"><span className="grid h-8 w-8 place-items-center rounded-[8px] bg-[var(--ds-primary)] text-white">I</span>IOT PLATFORM</Link>
    <nav aria-label="Primary navigation" className="hidden items-center lg:flex">{publicNavigation.map(item=><NavLink key={item.to} to={item.to} end={item.to==="/"} className={navClass}>{item.label}</NavLink>)}</nav>
    <div className="hidden items-center gap-2 lg:flex"><Link to="/login"><Button variant="ghost">Login</Button></Link><Link to="/register"><Button>Get Started</Button></Link></div>
    <button type="button" onClick={()=>setOpen(v=>!v)} aria-expanded={open} aria-controls="mobile-navigation" aria-label="Toggle navigation" className="rounded-[8px] p-2 text-[var(--ds-text-muted)] hover:bg-white/[.05] lg:hidden">{open?<X size={20}/>:<Menu size={20}/>}</button>
   </div>
   {open&&<nav id="mobile-navigation" aria-label="Mobile navigation" className="border-t border-[var(--ds-border-subtle)] px-4 py-3 lg:hidden">{publicNavigation.map(item=><NavLink key={item.to} to={item.to} end={item.to==="/"} onClick={()=>setOpen(false)} className={({isActive})=>`${navClass({isActive})} block`}>{item.label}</NavLink>)}<div className="mt-3 grid grid-cols-2 gap-2"><Link to="/login" onClick={()=>setOpen(false)}><Button variant="outline" className="w-full">Login</Button></Link><Link to="/register" onClick={()=>setOpen(false)}><Button className="w-full">Get Started</Button></Link></div></nav>}
  </header>
  <main>{children??<Outlet/>}</main>
  <footer className="border-t border-[var(--ds-border-subtle)] bg-[var(--ds-surface)]"><div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 sm:px-6 md:flex-row md:items-center md:justify-between"><div><p className="text-sm font-semibold">IOT PLATFORM</p><p className="mt-1 text-xs text-[var(--ds-text-subtle)]">Connected operations, from device to decision.</p></div><nav aria-label="Footer navigation" className="flex flex-wrap gap-x-4 gap-y-2">{publicFooterNavigation.map(item=><Link key={item.to} to={item.to} className="text-xs text-[var(--ds-text-muted)] hover:text-[var(--ds-text)]">{item.label}</Link>)}</nav></div></footer>
 </div>;
}
