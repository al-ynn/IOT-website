import {useEffect,useState,type FormEvent} from "react";
import {Eye,Plus,RefreshCw,Search,ShieldCheck} from "lucide-react";
import {Link,useSearchParams} from "react-router-dom";
import AdminPageHeader from "../../components/admin/layout/AdminPageHeader";
import {Button,Card,CardContent,DataTable,EmptyState,ErrorState,Input,Modal,Select,Skeleton,StatusIndicator,type DataTableColumn} from "../../components/ui";
import {createGlobalDevice,getAdminOrganizations,listGlobalDevices} from "../../services/admin.service";
import {listAdminLocations} from "../../services/location.service";
import type {LocationReference} from "../../types/location";
import type {AdminGlobalDevice,AdminGlobalDeviceFilters,AdminGlobalDeviceListResponse,AdminOrganization} from "../../types/admin";

const dateTime=(value:string|null)=>value?new Intl.DateTimeFormat(undefined,{dateStyle:"medium",timeStyle:"short"}).format(new Date(value)):"Never";

export default function GlobalDevices(){
  const [params,setParams]=useSearchParams();
  const [result,setResult]=useState<AdminGlobalDeviceListResponse|null>(null);
  const [organizations,setOrganizations]=useState<AdminOrganization[]>([]);
  const [error,setError]=useState("");
  const [loading,setLoading]=useState(true);
  const [refresh,setRefresh]=useState(0);
  const [search,setSearch]=useState(params.get("search")??"");
  const [createOpen,setCreateOpen]=useState(false);
  const [saving,setSaving]=useState(false);
  const [locations,setLocations]=useState<LocationReference[]>([]);
  const [createForm,setCreateForm]=useState({organization_id:"",name:"",type:"",serialNumber:"",protocol:"mqtt",location_id:"",macAddress:""});
  const query=params.toString();

  useEffect(()=>{
    let current=true;
    const filters=Object.fromEntries(new URLSearchParams(query)) as unknown as AdminGlobalDeviceFilters;
    Promise.all([listGlobalDevices(filters),getAdminOrganizations()]).then(([devices,nextOrganizations])=>{if(current){setResult(devices);setOrganizations(nextOrganizations);}}).catch(()=>{if(current)setError("Global device inventory could not be loaded.");}).finally(()=>{if(current)setLoading(false);});
    return()=>{current=false;};
  },[query,refresh]);

  useEffect(()=>{if(!createOpen||!createForm.organization_id)return;let active=true;listAdminLocations("",createForm.organization_id).then(page=>{if(active)setLocations(page.data)}).catch(()=>{if(active)setLocations([])});return()=>{active=false};},[createOpen,createForm.organization_id]);

  const setFilter=(name:string,value:string)=>{const next=new URLSearchParams(params);if(value)next.set(name,value);else next.delete(name);if(name!=="page")next.delete("page");setLoading(true);setError("");setParams(next);};
  const reload=()=>{setLoading(true);setError("");setRefresh(value=>value+1);};
  const submit=(event:FormEvent)=>{event.preventDefault();setFilter("search",search.trim());};
  const create=async()=>{if(saving)return;setSaving(true);setError("");try{await createGlobalDevice(createForm);setCreateOpen(false);setCreateForm({organization_id:"",name:"",type:"",serialNumber:"",protocol:"mqtt",location_id:"",macAddress:""});reload();}catch{setError("Device could not be created. Check the organization and unique identifier.");}finally{setSaving(false);}};
  const columns:DataTableColumn<AdminGlobalDevice>[]=[
    {key:"device",header:"Device",render:row=><div><Link className="font-medium text-[var(--ds-text)] hover:text-[var(--ds-primary)]" to={`/admin/devices/${row.id}`}>{row.name}</Link><div className="text-[11px]">{row.type}</div></div>},
    {key:"identifier",header:"Identifier",render:row=>row.identifier??"—"},
    {key:"organization",header:"Organization",render:row=>row.organization.name},
    {key:"creator",header:"Creator",render:row=>row.creator?.name??"Unknown"},
    {key:"status",header:"Status",render:row=><StatusIndicator status={row.status}/>},
    {key:"activity",header:"Last Activity",render:row=>dateTime(row.lastActivity)},
    {key:"assigned",header:"Assigned Staff",align:"center",render:row=><span className="tabular-nums">{row.assignedStaffCount}</span>},
    {key:"created",header:"Created",render:row=>dateTime(row.createdAt)},
    {key:"actions",header:"Actions",align:"right",render:row=><div className="flex justify-end gap-2"><Link to={`/admin/devices/${row.id}`} className="inline-flex h-7 items-center gap-1 rounded-md border border-[var(--ds-border)] px-2 text-xs hover:bg-white/[.04]"><Eye size={13}/>View</Link><Link to={`/admin/device-access?device_id=${row.id}`} className="inline-flex h-7 items-center gap-1 rounded-md border border-[var(--ds-border)] px-2 text-xs hover:bg-white/[.04]"><ShieldCheck size={13}/>Access</Link></div>},
  ];
  const page=result?.meta.current_page??1;

  return <div className="space-y-5"><AdminPageHeader title="Global Devices" description="Platform-wide device inventory and administration." action={<span className="flex gap-2"><Button size="small" leadingIcon={<Plus size={14}/>} onClick={()=>setCreateOpen(true)}>Create Device</Button><Button variant="outline" size="small" onClick={()=>setFilter("recent",params.get("recent")?"":"1")}>{params.get("recent")?"All Devices":"Recently Created"}</Button><Button variant="outline" size="small" loading={loading} leadingIcon={<RefreshCw size={14}/>} onClick={reload}>Refresh</Button></span>}/>
    <Card><CardContent><form onSubmit={submit} className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_220px_150px_180px_130px_auto]"><Input aria-label="Search devices" placeholder="Device, identifier, organization" value={search} leadingIcon={<Search size={14}/>} onChange={event=>setSearch(event.target.value)}/><Select aria-label="Filter organization" value={params.get("organization_id")??""} onChange={event=>setFilter("organization_id",event.target.value)}><option value="">All organizations</option>{organizations.map(item=><option key={item.id} value={item.id}>{item.name}</option>)}</Select><Select aria-label="Filter status" value={params.get("status")??""} onChange={event=>setFilter("status",event.target.value)}><option value="">All statuses</option><option value="online">Online</option><option value="offline">Offline</option></Select><Select aria-label="Sort devices" value={params.get("sort")??"created_at"} onChange={event=>setFilter("sort",event.target.value)}><option value="created_at">Created</option><option value="name">Name</option><option value="identifier">Identifier</option><option value="status">Status</option><option value="last_seen">Last activity</option><option value="organization">Organization</option></Select><Select aria-label="Sort direction" value={params.get("direction")??"desc"} onChange={event=>setFilter("direction",event.target.value)}><option value="desc">Descending</option><option value="asc">Ascending</option></Select><Button type="submit">Search</Button></form></CardContent></Card>
    {error?<Card><ErrorState description={error} retry={reload}/></Card>:loading&&!result?<Skeleton className="h-96"/>:result&&<><DataTable rows={result.data} columns={columns} getRowKey={row=>row.id} caption="Platform-wide devices" empty={<EmptyState title={result.meta.total===0&&!query?"No devices are registered on the platform.":"No devices match the selected filters."}/>}/><div className="flex flex-wrap items-center justify-between gap-3 text-xs text-[var(--ds-text-muted)]"><span>{result.meta.total} devices · page {page} of {result.meta.last_page}</span><div className="flex items-center gap-2"><Select aria-label="Rows per page" className="w-28" value={String(result.meta.per_page)} onChange={event=>setFilter("per_page",event.target.value)}><option value="25">25 rows</option><option value="50">50 rows</option><option value="100">100 rows</option></Select><Button size="compact" variant="outline" disabled={page<=1} onClick={()=>setFilter("page",String(page-1))}>Previous</Button><Button size="compact" variant="outline" disabled={page>=result.meta.last_page} onClick={()=>setFilter("page",String(page+1))}>Next</Button></div></div></>}
    <Modal open={createOpen} onClose={()=>setCreateOpen(false)} title="Create Device" description="Create an immediately usable platform record in the selected Organization. It starts offline until real telemetry arrives." footer={<><Button variant="ghost" onClick={()=>setCreateOpen(false)} disabled={saving}>Cancel</Button><Button loading={saving} disabled={!createForm.organization_id||!createForm.name||!createForm.type||!createForm.serialNumber} onClick={()=>void create()}>Create Device</Button></>}><div className="space-y-3"><Select label="Organization" required value={createForm.organization_id} onChange={event=>setCreateForm(value=>({...value,organization_id:event.target.value}))}><option value="">Select organization</option>{organizations.map(item=><option key={item.id} value={item.id}>{item.name}</option>)}</Select><Input label="Name" required value={createForm.name} onChange={event=>setCreateForm(value=>({...value,name:event.target.value}))}/><Input label="Type" required value={createForm.type} onChange={event=>setCreateForm(value=>({...value,type:event.target.value}))}/><Input label="Device identifier / serial number" required value={createForm.serialNumber} onChange={event=>setCreateForm(value=>({...value,serialNumber:event.target.value}))}/><Select label="Protocol" value={createForm.protocol} onChange={event=>setCreateForm(value=>({...value,protocol:event.target.value}))}><option value="mqtt">MQTT</option><option value="http">HTTP</option><option value="coap">CoAP</option></Select><Select label="Location" value={createForm.location_id} onChange={event=>setCreateForm(value=>({...value,location_id:event.target.value}))}><option value="">Unassigned</option>{locations.map(location=><option key={location.id} value={location.id} disabled={!location.isAssignable}>{location.name}{location.isAssignable?"":" - Disabled"}</option>)}</Select><Input label="MAC address" value={createForm.macAddress} onChange={event=>setCreateForm(value=>({...value,macAddress:event.target.value}))}/></div></Modal>
  </div>;
}
