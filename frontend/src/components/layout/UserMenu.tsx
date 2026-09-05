import {useEffect,useRef,useState} from "react";
import {LogOut,ShieldCheck} from "lucide-react";
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
    <button type="button" aria-label="Open user menu" aria-expanded={open} onClick={()=>setOpen(value=>!value)} className="flex h-9 items-center gap-2 rounded-[9px] border border-[var(--ds-border-subtle)] bg-[var(--ds-card)] px-2 hover:border-[var(--ds-primary-outline)] hover:bg-[var(--ds-primary-surface)] focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)]">
      <span className="grid h-6 w-6 place-items-center rounded-[7px] text-[11px] font-bold text-white" style={{background:"linear-gradient(135deg, var(--ds-primary), var(--ds-primary-strong))",boxShadow:"0 2px 8px -2px var(--ds-primary-glow)"}}>{user?.name?.charAt(0).toUpperCase()??"U"}</span>
      <span className="hidden max-w-28 truncate text-xs font-medium sm:block">{user?.name??"Account"}</span>
    </button>
    {open&&<div className="luminous-top absolute right-0 top-11 z-50 w-60 overflow-hidden rounded-[12px] border border-[var(--ds-border)] bg-[var(--ds-overlay-panel)] p-1.5" style={{boxShadow:"var(--ds-shadow-lg)"}}>
      <div className="mb-1 flex items-center gap-2.5 rounded-[8px] border border-[var(--ds-border-subtle)] bg-[var(--ds-card-alt)] px-3 py-2.5">
        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-[8px] text-xs font-bold text-white" style={{background:"linear-gradient(135deg, var(--ds-primary), var(--ds-primary-strong))"}}>{user?.name?.charAt(0).toUpperCase()??"U"}</span>
        <div className="min-w-0">
          <p className="truncate text-xs font-semibold text-[var(--ds-text)]">{user?.name}</p>
          <p className="truncate text-[10.5px] text-[var(--ds-text-subtle)]">{user?.email}</p>
        </div>
      </div>
      <p className="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-medium uppercase tracking-[0.14em] text-[var(--ds-text-subtle)]"><ShieldCheck size={11} className="text-[var(--ds-primary)]"/>{user?.platformRole==="platform_admin"?"Platform Admin":"Staff"}</p>
      <button type="button" onClick={signOut} className="flex w-full items-center gap-2 rounded-[7px] px-3 py-2 text-xs text-[var(--ds-danger)] hover:bg-[var(--ds-danger-surface)]"><LogOut size={14}/>Logout</button>
    </div>}
  </div>;
}
