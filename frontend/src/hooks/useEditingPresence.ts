import {useEffect,useState} from 'react';
import {heartbeatEditingLease,listEditingLeases,releaseEditingLease,startEditingLease,type EditingPresenceEditor} from '../services/editing-presence.service';

export function useEditingPresence(type:string|undefined,id:string|undefined,active:boolean){
 const[editors,setEditors]=useState<EditingPresenceEditor[]>([]);const[unavailable,setUnavailable]=useState(false);
 useEffect(()=>{
  if(!active||!type||!id)return;
  let mounted=true;let token:string|undefined;
  const refresh=async()=>{if(document.visibilityState==='hidden')return;try{const next=token?await heartbeatEditingLease(type,id,token):await startEditingLease(type,id);if(!mounted)return;token=next.lease.token;setEditors(next.editors);setUnavailable(false)}catch{if(mounted)setUnavailable(true)}};
  const poll=async()=>{if(document.visibilityState==='hidden')return;try{const next=await listEditingLeases(type,id);if(mounted)setEditors(next)}catch{if(mounted)setUnavailable(true)}};
  const visible=()=>{if(document.visibilityState==='visible'){void refresh();void poll()}};
  void refresh();const initialPoll=window.setTimeout(()=>void poll(),1000);const timer=window.setInterval(()=>{void refresh();void poll()},30000);document.addEventListener('visibilitychange',visible);
  return()=>{mounted=false;window.clearTimeout(initialPoll);window.clearInterval(timer);document.removeEventListener('visibilitychange',visible);if(token)void releaseEditingLease(type,id,token).catch(()=>undefined)};
 },[type,id,active]);
 return{otherEditors:active?editors.filter(x=>!x.sameUser):[],presenceUnavailable:active&&unavailable};
}
