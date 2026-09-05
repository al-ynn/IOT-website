/* eslint-disable react-refresh/only-export-components */
import {createContext,useCallback,useContext,useMemo,useState,type ReactNode} from "react";
import {AlertTriangle,HelpCircle,ShieldAlert} from "lucide-react";
import {Button,Modal,Toast,type ToastTone} from "../components/ui";

interface Notice{id:string;title:string;description?:string;tone:ToastTone}
interface Confirmation{title:string;description:string;confirmLabel:string;tone:"default"|"warning"|"danger";resolve:(answer:boolean)=>void}
interface FeedbackValue{notify:(notice:Omit<Notice,"id">)=>void;confirm:(options:{title:string;description:string;confirmLabel?:string;tone?:Confirmation["tone"]})=>Promise<boolean>}
const FeedbackContext=createContext<FeedbackValue|null>(null);

export function FeedbackProvider({children}:{children:ReactNode}){
 const[notices,setNotices]=useState<Notice[]>([]),[confirmation,setConfirmation]=useState<Confirmation|null>(null);
 const dismiss=useCallback((id:string)=>setNotices(items=>items.filter(item=>item.id!==id)),[]);
 const notify=useCallback((notice:Omit<Notice,"id">)=>{const id=crypto.randomUUID();setNotices(items=>[...items.slice(-3),{...notice,id}]);window.setTimeout(()=>dismiss(id),notice.tone==="error"?8000:5000)},[dismiss]);
 const confirm=useCallback((options:{title:string;description:string;confirmLabel?:string;tone?:Confirmation["tone"]})=>new Promise<boolean>(resolve=>setConfirmation({...options,confirmLabel:options.confirmLabel??"Confirm",tone:options.tone??"default",resolve})),[]);
 const close=(answer:boolean)=>{confirmation?.resolve(answer);setConfirmation(null)};
 const value=useMemo(()=>({notify,confirm}),[notify,confirm]);
 const Icon=confirmation?.tone==="danger"?ShieldAlert:confirmation?.tone==="warning"?AlertTriangle:HelpCircle;
 return <FeedbackContext.Provider value={value}>{children}<div role="region" aria-label="Application notifications" className="pointer-events-none fixed right-3 top-3 z-[120] flex w-[calc(100%-1.5rem)] max-w-sm flex-col gap-2 sm:right-5 sm:top-5">{notices.map(notice=><div key={notice.id} className="pointer-events-auto"><Toast {...notice} onDismiss={()=>dismiss(notice.id)}/></div>)}</div><Modal open={confirmation!==null} onClose={()=>close(false)} title={confirmation?.title??"Confirm action"} footer={<><Button variant="ghost" onClick={()=>close(false)}>Cancel</Button><Button variant={confirmation?.tone==="danger"?"danger":"primary"} onClick={()=>close(true)}>{confirmation?.confirmLabel}</Button></>}><div className="flex gap-3"><span className={`grid h-10 w-10 shrink-0 place-items-center rounded-full ${confirmation?.tone==="danger"?"bg-red-500/15 text-red-400":confirmation?.tone==="warning"?"bg-amber-500/15 text-amber-400":"bg-blue-500/15 text-blue-400"}`}><Icon size={20}/></span><p className="pt-1 text-sm leading-6 text-[var(--ds-text-muted)]">{confirmation?.description}</p></div></Modal></FeedbackContext.Provider>;
}
export function useFeedback(){const value=useContext(FeedbackContext);if(!value)throw new Error("useFeedback must be used within FeedbackProvider");return value}
