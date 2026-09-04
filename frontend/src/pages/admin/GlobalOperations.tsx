import {useCallback,useEffect,useState,type FormEvent} from "react";
import {Building2,Radio,RefreshCw,Search,Wifi,WifiOff} from "lucide-react";
import AdminPageHeader from "../../components/admin/layout/AdminPageHeader";
import PlatformMetricCard from "../../components/admin/dashboard/PlatformMetricCard";
import {Button,Card,CardContent,CardHeader,EmptyState,ErrorState,Input,Select,Skeleton,StatusIndicator} from "../../components/ui";
import {getGlobalOperationsOverview} from "../../services/admin.service";
import type {GlobalDeviceActivity,GlobalOperationsFilters,GlobalOperationsOverview} from "../../types/admin";
import DashboardWorkspace from "../../components/dashboard/DashboardWorkspace";
import {getGlobalDashboard,updateGlobalDashboard} from "../../services/dashboard.service";

const dateTime=(value:string|null)=>value?new Intl.DateTimeFormat(undefined,{dateStyle:"medium",timeStyle:"short"}).format(new Date(value)):"Never";

function ActivityTable({rows,empty}:{rows:GlobalDeviceActivity[];empty:string}){
  if(!rows.length)return <EmptyState title={empty}/>;
  return <div className="overflow-x-auto"><table className="w-full min-w-[520px] text-left text-xs"><thead className="text-[var(--ds-text-muted)]"><tr><th className="px-4 py-2 font-medium">Device</th><th className="px-4 py-2 font-medium">Organization</th><th className="px-4 py-2 font-medium">Status</th><th className="px-4 py-2 font-medium">Last activity</th></tr></thead><tbody className="divide-y divide-[var(--ds-border-subtle)]">{rows.map(row=><tr key={row.id}><td className="px-4 py-2.5 font-medium text-[var(--ds-text)]">{row.name}</td><td className="px-4 py-2.5 text-[var(--ds-text-muted)]">{row.organization.name}</td><td className="px-4 py-2.5"><StatusIndicator status={row.status}/></td><td className="px-4 py-2.5 text-[var(--ds-text-muted)]">{dateTime(row.lastActivity)}</td></tr>)}</tbody></table></div>;
}

export default function GlobalOperations(){const load=useCallback(()=>getGlobalDashboard(),[]);const save=useCallback((dashboard:Parameters<typeof updateGlobalDashboard>[0])=>updateGlobalDashboard(dashboard),[]);return <DashboardWorkspace load={load} save={save} fallback={<StaticGlobalOperations/>}/>}

function StaticGlobalOperations(){
  const [data,setData]=useState<GlobalOperationsOverview|null>(null);
  const [error,setError]=useState("");
  const [loading,setLoading]=useState(true);
  const [draft,setDraft]=useState<GlobalOperationsFilters>({});
  const [filters,setFilters]=useState<GlobalOperationsFilters>({});

  const load=async()=>{setLoading(true);setError("");try{setData(await getGlobalOperationsOverview(filters));}catch{setError("Global operations data could not be loaded.");}finally{setLoading(false);}};
  useEffect(()=>{
    let current=true;
    getGlobalOperationsOverview(filters).then(result=>{if(current)setData(result);}).catch(()=>{if(current)setError("Global operations data could not be loaded.");}).finally(()=>{if(current)setLoading(false);});
    return()=>{current=false;};
  },[filters]);
  const submit=(event:FormEvent)=>{event.preventDefault();setFilters({...draft,search:draft.search?.trim()||undefined});};

  return <div className="space-y-5">
    <AdminPageHeader title="Global Operations" description="Platform-wide device monitoring and operational status." action={<Button variant="outline" size="small" loading={loading} leadingIcon={<RefreshCw size={14}/>} onClick={()=>void load()}>Refresh</Button>}/>
    <Card><CardContent><form onSubmit={submit} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(220px,1fr)_220px_160px_auto] lg:items-end"><Input label="Search" placeholder="Device, identifier, or organization" value={draft.search??""} leadingIcon={<Search size={14}/>} onChange={event=>setDraft(current=>({...current,search:event.target.value}))}/><Select label="Organization" value={draft.organization_id??""} onChange={event=>setDraft(current=>({...current,organization_id:event.target.value||undefined}))}><option value="">All organizations</option>{data?.filters.organizations.map(item=><option key={item.id} value={item.id}>{item.name}</option>)}</Select><Select label="Status" value={draft.status??""} onChange={event=>setDraft(current=>({...current,status:(event.target.value||undefined) as GlobalOperationsFilters["status"]}))}><option value="">All statuses</option><option value="online">Online</option><option value="offline">Offline</option></Select><Button type="submit" size="default">Apply filters</Button></form></CardContent></Card>
    {error?<Card><ErrorState description={error} retry={()=>void load()}/></Card>:loading&&!data?<div className="space-y-4"><div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{Array.from({length:4},(_,index)=><Skeleton key={index} className="h-24"/>)}</div><Skeleton className="h-64"/></div>:data&&<>
      <div className="grid gap-3 grid-cols-2 xl:grid-cols-4"><PlatformMetricCard label="Total Devices" value={data.devices.total} icon={Radio}/><PlatformMetricCard label="Online" value={data.devices.online} icon={Wifi}/><PlatformMetricCard label="Offline" value={data.devices.offline} icon={WifiOff}/><PlatformMetricCard label="Organizations" value={data.organizations.total} icon={Building2}/></div>
      <Card><CardHeader title="Operational failures — last 24 hours" description="Counts from persisted technical records; no inferred health or criticality scores."/><CardContent className="grid grid-cols-2 gap-3 lg:grid-cols-4">{[["Error events",data.operationalWindow.errors],["Crash reports",data.operationalWindow.crashes],["Firmware failures",data.operationalWindow.failedFirmwareDeployments],["Webhook failures",data.operationalWindow.failedWebhookDeliveries]].map(([label,value])=><div key={label} className="rounded-lg border border-[var(--ds-border-subtle)] p-3"><p className="text-xs text-[var(--ds-text-muted)]">{label}</p><p className="mt-1 text-xl font-semibold tabular-nums">{value}</p></div>)}</CardContent></Card>
      {data.devices.total===0?<Card><EmptyState title="No devices are registered on the platform."/></Card>:<>
        <div className="grid gap-4 xl:grid-cols-2">
          <Card><CardHeader title="Device status" description="Current status from the canonical device record."/><CardContent><div role="img" aria-label={`${data.devices.online} online and ${data.devices.offline} offline devices`} className="space-y-3"><div className="flex h-3 overflow-hidden rounded-full bg-slate-500/20">{data.devices.online>0&&<span className="bg-emerald-500" style={{width:`${data.devices.online/data.devices.total*100}%`}}/>}</div><div className="flex justify-between text-xs text-[var(--ds-text-muted)]"><span>{data.devices.online} online</span><span>{data.devices.offline} offline</span></div></div></CardContent></Card>
          <Card><CardHeader title="Organization coverage" description={`${data.organizations.withDevices} of ${data.organizations.total} organizations have devices.`}/><div className="overflow-x-auto"><table className="w-full min-w-[560px] text-left text-xs"><thead className="text-[var(--ds-text-muted)]"><tr><th className="px-4 py-2 font-medium">Organization</th><th className="px-4 py-2 font-medium">Devices</th><th className="px-4 py-2 font-medium">Online</th><th className="px-4 py-2 font-medium">Offline</th><th className="px-4 py-2 font-medium">Staff</th></tr></thead><tbody className="divide-y divide-[var(--ds-border-subtle)]">{data.organizations.items.map(item=><tr key={item.id}><td className="px-4 py-2.5 font-medium">{item.name}</td><td className="px-4 py-2.5">{item.totalDevices}</td><td className="px-4 py-2.5">{item.onlineDevices}</td><td className="px-4 py-2.5">{item.offlineDevices}</td><td className="px-4 py-2.5">{item.staffCount}</td></tr>)}</tbody></table></div></Card>
        </div>
        <div className="grid gap-4 xl:grid-cols-2"><Card><CardHeader title="Recent device activity" description="Ten devices with the latest recorded check-in."/><ActivityTable rows={data.recentDevices} empty="No device activity has been recorded."/></Card><Card><CardHeader title="Offline devices" description="Up to ten offline devices, never-seen devices first."/><ActivityTable rows={data.offlineDevices} empty="No offline devices match the current filters."/></Card></div>
        <Card><CardHeader title="Global device preview" description="A bounded monitoring preview. Device management remains in Global Devices."/><div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-xs"><thead className="text-[var(--ds-text-muted)]"><tr><th className="px-4 py-2 font-medium">Device</th><th className="px-4 py-2 font-medium">Identifier</th><th className="px-4 py-2 font-medium">Organization</th><th className="px-4 py-2 font-medium">Status</th><th className="px-4 py-2 font-medium">Last activity</th><th className="px-4 py-2 font-medium">Assigned staff</th></tr></thead><tbody className="divide-y divide-[var(--ds-border-subtle)]">{data.devicePreview.map(row=><tr key={row.id}><td className="px-4 py-2.5 font-medium">{row.name}</td><td className="px-4 py-2.5 text-[var(--ds-text-muted)]">{row.identifier??"—"}</td><td className="px-4 py-2.5">{row.organization.name}</td><td className="px-4 py-2.5"><StatusIndicator status={row.status}/></td><td className="px-4 py-2.5 text-[var(--ds-text-muted)]">{dateTime(row.lastActivity)}</td><td className="px-4 py-2.5 tabular-nums">{row.assignedStaffCount}</td></tr>)}</tbody></table></div></Card>
      </>}
    </>}
  </div>;
}
