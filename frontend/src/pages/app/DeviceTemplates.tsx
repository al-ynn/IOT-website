import {useCallback,useEffect,useState} from "react";
import {Link,useNavigate} from "react-router-dom";
import {Copy,Plus} from "lucide-react";
import {BodyText,Button,Card,CardContent,EmptyState,ErrorState,Input,LoadingState,Modal,PageTitle,Select,Textarea} from "../../components/ui";
import {usePermissions} from "../../context/PermissionContext";
import type {DeviceTemplate} from "../../types/device";
import {createDeviceTemplate,duplicateDeviceTemplate,listDeviceTemplates} from "../../services/device-template.service";

const hardware=["sensor","gateway","esp32","esp8266","raspberry_pi","arduino","industrial_controller"] as const;
const connections=["mqtt","https","wifi","ethernet","cellular","lorawan","bluetooth"] as const;

export default function DeviceTemplates(){
 const {can}=usePermissions();const manage=can("device.manage");const navigate=useNavigate();
 const [items,setItems]=useState<DeviceTemplate[]>([]);const [search,setSearch]=useState("");const [loading,setLoading]=useState(true);const [error,setError]=useState("");const [open,setOpen]=useState(false);const [saving,setSaving]=useState(false);
 const [form,setForm]=useState({name:"",description:"",device_type:"sensor",protocol:"mqtt"});
 const load=useCallback(async()=>{setLoading(true);setError("");try{setItems((await listDeviceTemplates(search)).data)}catch{setError("Device templates could not be loaded.")}finally{setLoading(false)}},[search]);
 useEffect(()=>{const timer=window.setTimeout(()=>void load(),250);return()=>window.clearTimeout(timer)},[load]);
 const create=async()=>{setSaving(true);setError("");try{const item=await createDeviceTemplate({...form,name:form.name.trim(),description:form.description.trim()||null});setOpen(false);navigate(`/app/developer/templates/${item.id}/home`)}catch{setError("Template could not be created. Check the field values.")}finally{setSaving(false)}};
 const duplicate=async(item:DeviceTemplate)=>{const copy=await duplicateDeviceTemplate(item.id);navigate(`/app/developer/templates/${copy.id}/home`)};
 return <div className="mx-auto max-w-6xl space-y-5">
  <header className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><PageTitle>Device Templates</PageTitle><BodyText className="mt-1">Reusable blueprints for Device data and dashboards.</BodyText></div>{manage&&<Button leadingIcon={<Plus size={15}/>} onClick={()=>setOpen(true)}>Create template</Button>}</header>
  <Input aria-label="Search templates" placeholder="Search templates" value={search} onChange={event=>setSearch(event.target.value)}/>
  {loading?<LoadingState label="Loading templates..."/>:error?<ErrorState description={error} retry={load}/>:items.length===0?<Card><EmptyState title="No Device templates" description={manage?"Create a reusable blueprint for a Device family.":"No templates are available in this organization."} action={manage?<Button size="small" onClick={()=>setOpen(true)}>Create template</Button>:undefined}/></Card>:<div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">{items.map(item=><Card key={item.id} variant="interactive"><CardContent><div className="flex items-start justify-between gap-3"><div><Link className="font-semibold hover:text-[var(--ds-primary)]" to={`/app/developer/templates/${item.id}/home`}>{item.name}</Link><p className="mt-1 text-xs text-[var(--ds-text-muted)]">{item.description||"No description"}</p></div><span className="text-xs text-[var(--ds-text-subtle)]">{item.parameterCount} datastreams</span></div><div className="mt-4 flex items-center justify-between text-xs text-[var(--ds-text-muted)]"><span>{item.deviceType} · {item.protocol}</span>{manage&&<Button size="compact" variant="ghost" aria-label={`Duplicate ${item.name}`} onClick={()=>void duplicate(item)}><Copy size={14}/></Button>}</div></CardContent></Card>)}</div>}
  <Modal open={open} onClose={()=>setOpen(false)} title="Create new template" description="Start a reusable Device blueprint." footer={<><Button variant="ghost" onClick={()=>setOpen(false)}>Cancel</Button><Button disabled={!form.name.trim()} loading={saving} onClick={()=>void create()}>Create</Button></>}>
   <div className="space-y-3"><Input label="Name" required maxLength={50} helperText={`${form.name.length} / 50`} value={form.name} onChange={event=>setForm(value=>({...value,name:event.target.value}))}/><div className="grid gap-3 sm:grid-cols-2"><Select label="Hardware" required value={form.device_type} onChange={event=>setForm(value=>({...value,device_type:event.target.value}))}>{hardware.map(value=><option key={value} value={value}>{value.replaceAll("_"," ")}</option>)}</Select><Select label="Connection type" required value={form.protocol} onChange={event=>setForm(value=>({...value,protocol:event.target.value}))}>{connections.map(value=><option key={value} value={value}>{value.toUpperCase()}</option>)}</Select></div><Textarea label="Description" maxLength={128} helperText={`${form.description.length} / 128`} value={form.description} onChange={event=>setForm(value=>({...value,description:event.target.value}))}/></div>
  </Modal>
 </div>
}
