import {useEffect,useRef,useState} from "react";
import {LogOut} from "lucide-react";
import {useNavigate} from "react-router-dom";
import {useAuth} from "../../hooks/useAuth";

export default function UserMenu(){
  const {user,logout}=useAuth();
  const navigate=useNavigate();
  const [open,setOpen]=useState(false);
  const root=useRef<HTMLDivElement>(null);
  useEffect(()=>{const close=(event:KeyboardEvent)=>{if(event.key==="Escape")setOpen(false)};document.addEventListener("keydown",close);return()=>document.removeEventListener("keydown",close)},[]);
  async function signOut(){setOpen(false);await logout();navigate("/login",{replace:true})}
  return <div ref={root} className="relative">
    <button type="button" aria-label="Open user menu" aria-expanded={open} onClick={()=>setOpen(value=>!value)} className="flex h-9 items-center gap-2 rounded-[8px] border border-[var(--ds-border-subtle)] bg-[var(--ds-surface)] px-2 hover:bg-[var(--ds-surface-elevated)] focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)]">
      <span className="grid h-6 w-6 place-items-center rounded-[6px] bg-[var(--ds-primary)]/15 text-xs font-semibold text-[var(--ds-primary)]">{user?.name?.charAt(0).toUpperCase()??"U"}</span>
      <span className="hidden max-w-28 truncate text-xs font-medium sm:block">{user?.name??"Account"}</span>
    </button>
    {open&&<div className="absolute right-0 top-11 z-50 w-56 overflow-hidden rounded-[10px] border border-[var(--ds-border)] bg-[var(--ds-surface-elevated)] p-1 shadow-2xl">
      <div className="border-b border-[var(--ds-border-subtle)] px-3 py-2">
        <p className="truncate text-xs font-medium">{user?.name}</p>
        <p className="truncate text-[11px] text-[var(--ds-text-subtle)]">{user?.platformRole==="platform_admin"?"Admin":"Staff"} · {user?.email}</p>
      </div>
      <button type="button" onClick={signOut} className="flex w-full items-center gap-2 rounded-[6px] px-3 py-2 text-xs text-[var(--ds-danger)] hover:bg-red-500/10"><LogOut size={14}/>Logout</button>
    </div>}
  </div>;
}
