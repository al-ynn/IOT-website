import {useEffect,useState} from "react";
import {useNavigate} from "react-router-dom";
import {Check} from "lucide-react";
import {createDevice} from "../../services/device.service";
import {listLocations} from "../../services/location.service";
import type {LocationReference} from "../../types/location";
import {BodyText,Button,Card,CardContent,Input,PageTitle,Select} from "../../components/ui";

const steps=["Identity","Connection","Location","Review"];

export default function AddDevice(){
 const navigate=useNavigate();
 const [step,setStep]=useState(0);
 const [form,setForm]=useState({name:"",type:"",serialNumber:"",protocol:"mqtt",location_id:"",macAddress:""});
 const [locations,setLocations]=useState<LocationReference[]>([]);
 const [saving,setSaving]=useState(false);
 const [error,setError]=useState("");
 useEffect(()=>{let active=true;listLocations().then(page=>{if(active)setLocations(page.data)}).catch(()=>{if(active)setError("Locations could not be loaded; you may leave the Device unassigned.")});return()=>{active=false}},[]);
 const set=(key:keyof typeof form)=>(event:React.ChangeEvent<HTMLInputElement|HTMLSelectElement>)=>setForm(value=>({...value,[key]:event.target.value}));
 function next(){setError("");if(step===0&&(!form.name.trim()||!form.type.trim())){setError("Enter a device name and type.");return;}if(step===1&&!form.serialNumber.trim()){setError("Enter a unique device identifier.");return;}setStep(value=>Math.min(3,value+1));}
 async function submit(){if(saving)return;setSaving(true);setError("");try{const device=await createDevice({...form,location_id:form.location_id||null});navigate(`/app/devices/${device.id}`,{replace:true});}catch{setError("Unable to create the device. Check the identifier and try again.");}finally{setSaving(false);}}
 return <div className="mx-auto max-w-3xl space-y-5"><div><PageTitle>Add device</PageTitle><BodyText className="mt-1">Connect a device through a guided four-step setup.</BodyText></div><ol aria-label="Device setup progress" className="grid grid-cols-4 gap-2">{steps.map((label,index)=><li key={label} className={`rounded-[8px] border p-2 text-[10px] sm:text-xs ${index===step?"border-[var(--ds-primary)] text-[var(--ds-text)]":"border-[var(--ds-border-subtle)] text-[var(--ds-text-subtle)]"}`}><span className="mr-1">{index<step?<Check size={12} className="inline"/>:`${index+1}.`}</span>{label}</li>)}</ol><Card><CardContent className="space-y-4">{step===0&&<><Input label="Device name" value={form.name} onChange={set("name")} required/><Input label="Device type" value={form.type} onChange={set("type")} required/></>}{step===1&&<><Input label="Device identifier / serial number" value={form.serialNumber} onChange={set("serialNumber")} required/><Select label="Connection protocol" value={form.protocol} onChange={set("protocol")}><option value="mqtt">MQTT</option><option value="http">HTTP</option><option value="coap">CoAP</option></Select></>}{step===2&&<><Select label="Location" value={form.location_id} onChange={set("location_id")}><option value="">Unassigned</option>{locations.map(location=><option key={location.id} value={location.id} disabled={!location.isAssignable}>{location.name}{location.isAssignable?"":" — Disabled"}</option>)}</Select><Input label="MAC address" value={form.macAddress} onChange={set("macAddress")} helperText="Optional"/></>}{step===3&&<dl className="grid gap-3 sm:grid-cols-2">{Object.entries(form).map(([key,value])=><div key={key} className="rounded-[8px] border border-[var(--ds-border-subtle)] p-3"><dt className="text-[10px] uppercase text-[var(--ds-text-subtle)]">{key.replace(/([A-Z])/g," $1")}</dt><dd className="mt-1 text-xs">{value||"Not provided"}</dd></div>)}</dl>}{error&&<p role="alert" className="text-xs text-[var(--ds-danger)]">{error}</p>}<div className="flex justify-between"><Button variant="ghost" onClick={()=>step?setStep(value=>value-1):navigate("/app/devices")} disabled={saving}>{step?"Back":"Cancel"}</Button>{step<3?<Button onClick={next}>Continue</Button>:<Button loading={saving} onClick={submit}>Register device</Button>}</div></CardContent></Card></div>;
}
