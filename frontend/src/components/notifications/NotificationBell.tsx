/* eslint-disable react-hooks/set-state-in-effect */
import {useCallback,useEffect,useRef,useState} from "react";
import {Bell,CheckCheck} from "lucide-react";
import {Link,useNavigate} from "react-router-dom";
import {Button,Drawer,EmptyState,ErrorState,Skeleton} from "../ui";
import {markNotificationRead,recentNotifications,unreadNotificationCount} from "../../services/notification.service";
import type {AppNotification} from "../../types/notification";

const ago=(value:string)=>{const seconds=Math.max(1,Math.floor((Date.now()-new Date(value).getTime())/1000));if(seconds<60)return `${seconds}s ago`;if(seconds<3600)return `${Math.floor(seconds/60)}m ago`;if(seconds<86400)return `${Math.floor(seconds/3600)}h ago`;return `${Math.floor(seconds/86400)}d ago`;};

export default function NotificationBell(){
  const navigate=useNavigate();
  const button=useRef<HTMLButtonElement>(null);
  const [open,setOpen]=useState(false);
  const [count,setCount]=useState(0);
  const [items,setItems]=useState<AppNotification[]>([]);
  const [loading,setLoading]=useState(false);
  const [error,setError]=useState("");
  const refresh=useCallback(async(includeItems=false)=>{try{setCount(await unreadNotificationCount());if(includeItems)setItems(await recentNotifications());setError("");}catch{if(includeItems)setError("Notifications could not be loaded.");}finally{setLoading(false);}},[]);
  useEffect(()=>{void refresh();const timer=window.setInterval(()=>void refresh(),60000);const focus=()=>void refresh();window.addEventListener("focus",focus);return()=>{window.clearInterval(timer);window.removeEventListener("focus",focus);};},[refresh]);
  const show=()=>{setOpen(true);setLoading(true);void refresh(true);};
  const close=()=>{setOpen(false);window.setTimeout(()=>button.current?.focus(),0);};
  const read=async(item:AppNotification)=>{if(!item.isRead){await markNotificationRead(item.id);setCount(v=>Math.max(0,v-1));setItems(v=>v.map(n=>n.id===item.id?{...n,isRead:true}:n));}};
  const view=async(item:AppNotification)=>{await read(item);close();if(item.deepLink)navigate(item.deepLink);};

  return <>
    <button ref={button} type="button" aria-label={`Notifications${count?`, ${count} unread`:""}`} onClick={show} className="relative grid h-9 w-9 place-items-center rounded-[9px] border border-[var(--ds-border-subtle)] text-[var(--ds-text-muted)] hover:border-[var(--ds-primary-outline)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)]">
      <Bell size={16}/>
      {count>0&&<span className="absolute -right-1 -top-1 grid min-w-[18px] place-items-center rounded-full border-2 border-[var(--ds-bg)] px-1 text-center text-[9px] font-bold text-white" style={{background:"linear-gradient(135deg, var(--ds-primary), var(--ds-primary-strong))",boxShadow:"0 0 10px -1px var(--ds-primary-glow)"}} aria-hidden="true">{count>99?"99+":count}</span>}
    </button>
    <Drawer open={open} onClose={close} title="Notifications" description={`${count} unread`} footer={<Link to="/app/notifications" onClick={close} className="text-xs font-medium text-[var(--ds-primary)] hover:text-[var(--ds-primary-hover)]">View all notifications</Link>}>
      {loading?<Skeleton className="h-48"/>:error?<ErrorState description={error} retry={()=>void refresh(true)}/>:items.length===0?<EmptyState title="No notifications"/>:(
        <ul className="space-y-2">{items.map(item=>(
          <li key={item.id} className={`luminous-top relative rounded-[10px] border p-3 transition ${item.isRead?"border-[var(--ds-border-subtle)] bg-[var(--ds-card)]":"border-[var(--ds-primary-outline)] bg-[var(--ds-primary-surface)]"}`}>
            <div className="flex gap-2.5">
              <span aria-hidden className={`mt-1 h-2 w-2 shrink-0 rounded-full ${item.isRead?"bg-[var(--ds-border-strong)]":"bg-[var(--ds-primary)]"}`} style={item.isRead?undefined:{boxShadow:"0 0 8px 1px var(--ds-primary-glow)"}}/>
              <div className="min-w-0 flex-1">
                <p className="break-words text-xs font-semibold text-[var(--ds-text)]">{item.title}{!item.isRead&&<span className="ml-2 rounded-[5px] bg-[var(--ds-primary)] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white">New</span>}</p>
                <p className="mt-1 break-words text-xs leading-5 text-[var(--ds-text-muted)]">{item.body}</p>
                <time title={new Date(item.createdAt).toLocaleString()} className="mt-1.5 block text-[10px] font-medium uppercase tracking-[0.08em] text-[var(--ds-text-subtle)]">{ago(item.createdAt)}</time>
              </div>
              {!item.isRead&&<button aria-label={`Mark ${item.title} read`} onClick={()=>void read(item)} className="grid h-7 w-7 shrink-0 place-items-center rounded-[7px] text-[var(--ds-text-subtle)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)]"><CheckCheck size={14}/></button>}
            </div>
            {item.deepLink&&<Button className="mt-2" size="compact" variant="outline" onClick={()=>void view(item)}>View</Button>}
          </li>
        ))}</ul>
      )}
    </Drawer>
  </>;
}
