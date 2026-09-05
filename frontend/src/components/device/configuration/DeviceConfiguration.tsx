import {useEffect,useRef,useState} from "react";
import {useEditingPresence} from "../../../hooks/useEditingPresence";
import {listLocations} from "../../../services/location.service";
import {updateDevice} from "../../../services/device.service";
import type {Device} from "../../../types/device";
import type {LocationReference} from "../../../types/location";
import {Button,Card,CardContent,CardHeader,Input,Select} from "../../ui";

export default function DeviceConfiguration({device,canManage,onUpdated}:{device:Device;canManage:boolean;onUpdated:(device:Device)=>void}){
 const [form,setForm]=useState({name:device.name,type:device.type,protocol:device.protocol,macAddress:device.macAddress??"",location_id:device.location?.id??""});
 const [locations,setLocations]=useState<LocationReference[]>([]);
 const [saving,setSaving]=useState(false);
 const [message,setMessage]=useState("");
 const pendingSave=useRef<{key:string;serialized:string}|null>(null);
 const {otherEditors,presenceUnavailable}=useEditingPresence("device",device.id,canManage);

 useEffect(()=>{if(!canManage)return;let active=true;listLocations().then(page=>{if(active)setLocations(page.data)}).catch(()=>{if(active)setMessage("Locations could not be loaded.")});return()=>{active=false}},[canManage]);

 async function save(){
  if(!device.id)return;
  setSaving(true);setMessage("");
  const command={...form,location_id:form.location_id||null,baseRevisionId:device.baseRevisionId??null};
  const serialized=JSON.stringify(command);
  const attempt=pendingSave.current?.serialized===serialized?pendingSave.current:{key:crypto.randomUUID(),serialized};
  pendingSave.current=attempt;
  try{onUpdated(await updateDevice(device.id,command,attempt.key));pendingSave.current=null;setMessage("Configuration saved.");}
  catch(error){const status=(error as{response?:{status?:number}}).response?.status;setMessage(status===409?"Newer changes are available. Reload before saving again.":"Unable to save configuration.");}
  finally{setSaving(false);}
 }

 return <Card><CardHeader title="Configuration" description={canManage?"Update supported operational device metadata.":"Read-only configuration for this assigned device."}/><CardContent className="grid gap-4 sm:grid-cols-2">
  {otherEditors.length>0&&<p className="sm:col-span-2 rounded border p-2 text-xs" role="status">Also editing: {otherEditors.map(editor=>editor.user.displayName).join(", ")}. Presence is advisory; saves still use revision conflict protection.</p>}
  {presenceUnavailable&&<p className="sm:col-span-2 text-xs text-[var(--ds-text-muted)]" role="status">Editing presence is temporarily unavailable. Save conflict protection remains active.</p>}
  <Input label="Name" value={form.name} readOnly={!canManage} onChange={event=>setForm({...form,name:event.target.value})}/>
  <Input label="Type" value={form.type} readOnly={!canManage} onChange={event=>setForm({...form,type:event.target.value})}/>
  {canManage?<Select label="Location" value={form.location_id} onChange={event=>setForm({...form,location_id:event.target.value})}><option value="">Unassigned</option>{device.location&&!device.location.isAssignable&&<option value={device.location.id}>{device.location.name} - Disabled (current)</option>}{locations.filter(location=>location.isAssignable&&location.id!==device.location?.id).map(location=><option key={location.id} value={location.id}>{location.name}</option>)}</Select>:<Input label="Location" value={device.location?.name??"Unassigned"} readOnly/>}
  <Input label="Protocol" value={form.protocol} readOnly={!canManage} onChange={event=>setForm({...form,protocol:event.target.value})}/>
  {canManage&&<div className="sm:col-span-2"><Button onClick={save} loading={saving}>Save configuration</Button></div>}
  {message&&<p className="text-xs text-[var(--ds-text-muted)] sm:col-span-2" role="status">{message}</p>}
 </CardContent></Card>;
}
