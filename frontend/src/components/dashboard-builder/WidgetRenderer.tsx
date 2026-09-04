import { useEffect, useState } from "react";
import type { Widget } from "../../types/dashboard";
import type { TelemetryRecord } from "../../types/telemetry";
import type { Device } from "../../types/device";
import { getTelemetryHistory } from "../../services/analytics.service";
import { getDevice, getDevices } from "../../services/device.service";
import { listDeviceEvents } from "../../services/operational-event.service";
import { listProvisioningSessions } from "../../services/provisioning.service";
import type {OperationalEvent} from "../../types/operational-event";
import type {ProvisioningSession} from "../../types/provisioning";
import { getGlobalOperationsOverview } from "../../services/admin.service";
import type {GlobalOperationsOverview} from "../../types/admin";
import DashboardWidget from "../dashboard/widgets/DashboardWidget";
import MetricWidget from "../dashboard/widgets/MetricWidget";
import ChartWidget from "../dashboard/widgets/ChartWidget";
import GaugeWidget from "../dashboard/widgets/GaugeWidget";
import StatusWidget from "../dashboard/widgets/StatusWidget";
import TableWidget from "../dashboard/widgets/TableWidget";
import DeviceWidget from "../dashboard/widgets/DeviceWidget";
import GeomapWidget from "../dashboard/widgets/GeomapWidget";
import ImageMapWidget from "../dashboard/widgets/ImageMapWidget";
import {hasWidgetRenderer} from "../dashboard/widgets/widgetRendererRegistry";
import {useDashboardStore} from "../../store/dashboard.store";
import { dashboardMapAssetUrl } from "../../services/dashboard.service";

export default function WidgetRenderer({ widget, editable = false, onComment, timeRange }: { widget: Widget; editable?: boolean; onComment?:()=>void; timeRange?:"1h"|"6h"|"24h"|"7d"|"30d" }) {
  const [records, setRecords] = useState<TelemetryRecord[]>([]);
  const [device, setDevice] = useState<Device | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [globalData,setGlobalData]=useState<GlobalOperationsOverview|null>(null);
  const [devices,setDevices]=useState<Device[]>([]);
  const [events,setEvents]=useState<OperationalEvent[]>([]);
  const [activations,setActivations]=useState<ProvisioningSession[]>([]);
  const source = widget.settings.datasource;
  const dashboardTimeRange=useDashboardStore(state=>state.timeRange);
  const fleetDevices = devices.filter(item => widget.settings.sourceMode === "explicit_devices" ? (widget.settings.deviceIds ?? []).includes(String(item.id)) : widget.settings.sourceMode === "template_devices" ? String(item.template?.id ?? "") === String(widget.settings.deviceTemplateId ?? "") : true);
  const fleetEvents = events.filter(item => { const id=String(item.device?.id ?? ""); if (widget.settings.sourceMode === "explicit_devices") return (widget.settings.deviceIds ?? []).includes(id); if (widget.settings.sourceMode === "template_devices") return fleetDevices.some(device=>String(device.id)===id); return true; });

  useEffect(() => {
    const deviceCollections=["device_count","device_table","geo_map","image_map","device_connection_map","metric_by_devices"];
    const eventWidgets=["event_count_tile","latest_events","event_count_chart","events_over_time","events_breakdown_over_time","events_by_organization","events_by_device","events_by_template"];
    if(deviceCollections.includes(widget.type)||eventWidgets.includes(widget.type)||widget.type==="activations") {let active=true;const timer=window.setTimeout(()=>{setLoading(true);setError("");const request=deviceCollections.includes(widget.type)?getDevices().then(value=>{if(active)setDevices(value)}):eventWidgets.includes(widget.type)?listDeviceEvents({per_page:100}).then(value=>{if(active)setEvents(value.data as OperationalEvent[])}):listProvisioningSessions({status:"completed",per_page:100}).then(value=>{if(active)setActivations(value.data)});request.catch(()=>{if(active)setError("Unable to load authorized widget data.")}).finally(()=>{if(active)setLoading(false)})},0);return()=>{active=false;window.clearTimeout(timer)};}
    if (widget.type.startsWith("global_")) {let active=true;const timer=window.setTimeout(()=>{setLoading(true);getGlobalOperationsOverview().then(value=>{if(active)setGlobalData(value)}).catch(()=>{if(active)setError("Unable to load global widget data.")}).finally(()=>{if(active)setLoading(false)})},0);return()=>{active=false;window.clearTimeout(timer)};}
    if (!source?.deviceId) return;
    let active = true;
    const timer = window.setTimeout(() => {
      setLoading(true);
      setError("");
      const request = widget.type === "device" || widget.type === "status"
        ? getDevice(source.deviceId).then((value) => { if (active) setDevice(value); })
        : source.telemetryKey
          ? getTelemetryHistory(source.telemetryKey, { deviceId: source.deviceId, range: timeRange??dashboardTimeRange??widget.settings.timeRange??"24h", interval: "hour", aggregation: "average" }).then((value) => {
              if (active) setRecords(value.points.map((point, index) => ({ id: `${point.timestamp}-${index}`, deviceId: source.deviceId, key: source.telemetryKey, value: point.value, unit: value.unit ?? "", recordedAt: point.timestamp })));
            })
          : Promise.resolve();
      request.catch(() => { if (active) setError("Unable to load widget data."); }).finally(() => { if (active) setLoading(false); });
    }, 0);
    return () => { active = false; window.clearTimeout(timer); };
  }, [source?.deviceId, source?.telemetryKey, widget.type,widget.settings.timeRange,timeRange,dashboardTimeRange]);

  const latest = records.at(-1) ?? null;
  let content: React.ReactNode = null;
  if (widget.type === "metric") content = <MetricWidget value={latest?.value ?? null} label={source?.telemetryKey} unit={latest?.unit ?? source?.unit} />;
  else if (widget.type === "chart") content = <ChartWidget records={records} type={widget.settings.chartType} />;
  else if (widget.type === "gauge" && latest) content = <GaugeWidget value={latest.value} min={widget.settings.minimum} max={widget.settings.maximum} unit={latest.unit ?? source?.unit} />;
  else if (widget.type === "table") content = <TableWidget records={records} />;
  else if (widget.type === "status" && device) content = <StatusWidget status={device.status === "online" ? "online" : device.status === "maintenance" ? "warning" : "offline"} label={device.status} />;
  else if (widget.type === "device" && device) content = <DeviceWidget device={device} />;
  else if(widget.type==="global_device_summary"&&globalData)content=<div className="grid grid-cols-2 gap-3 text-center text-xs"><div><strong className="block text-xl">{globalData.devices.total}</strong>Total</div><div><strong className="block text-xl">{globalData.devices.online}</strong>Online</div><div><strong className="block text-xl">{globalData.devices.offline}</strong>Offline</div><div><strong className="block text-xl">{globalData.organizations.total}</strong>Organizations</div></div>;
  else if(widget.type==="global_failure_summary"&&globalData)content=<div className="grid grid-cols-2 gap-3 text-center text-xs"><div><strong className="block text-xl">{globalData.operationalWindow.errors}</strong>Events</div><div><strong className="block text-xl">{globalData.operationalWindow.crashes}</strong>Crashes</div><div><strong className="block text-xl">{globalData.operationalWindow.failedFirmwareDeployments}</strong>Firmware</div><div><strong className="block text-xl">{globalData.operationalWindow.failedWebhookDeliveries}</strong>Webhooks</div></div>;
  else if(widget.type==="label")content=<p className="break-words text-2xl font-semibold">{widget.settings.staticValue||"Label"} {source?.unit}</p>;
  else if(widget.type==="device_count")content=<p className="text-4xl font-semibold tabular-nums">{fleetDevices.length}</p>;
  else if(widget.type==="device_table")content=fleetDevices.length?<div className="max-h-64 overflow-auto"><table className="w-full text-left text-xs"><thead><tr><th>Device</th><th>Status</th><th>Last seen</th></tr></thead><tbody>{fleetDevices.slice(0,widget.settings.rowLimit??25).map(item=><tr key={item.id}><td>{item.name}</td><td>{item.status}</td><td>{item.lastSeen?new Date(item.lastSeen).toLocaleString():"Never"}</td></tr>)}</tbody></table></div>:null;
  else if(widget.type==="device_connection_map")content=fleetDevices.length?<div role="img" aria-label="Authorized Device connection statuses" className="grid grid-cols-2 gap-2">{fleetDevices.slice(0,50).map(item=><div key={item.id} className="rounded border border-[var(--ds-border-subtle)] p-2 text-xs"><span className={`mr-2 inline-block h-2 w-2 rounded-full ${item.status==="online"?"bg-emerald-400":"bg-slate-500"}`}/>{item.name}<span className="block text-[var(--ds-text-muted)]">{item.status}</span></div>)}</div>:null;
  else if(widget.type==="geo_map")content=<GeomapWidget devices={fleetDevices}/>;
  else if(widget.type==="image_map")content=<ImageMapWidget devices={fleetDevices} editable={editable} onMarkersChange={markers=>useDashboardStore.getState().updateWidget(widget.id,{settings:{...widget.settings,markers}})} backgroundImageUrl={widget.settings.imageAssetId?dashboardMapAssetUrl(widget.settings.imageAssetId):undefined} markers={widget.settings.markers}/>;
  else if(widget.type==="switch"&&latest){const checked=Number(latest.value)>0;content=<div><button type="button" role="switch" aria-checked={checked} disabled className={`h-7 w-12 rounded-full ${checked?"bg-emerald-500":"bg-slate-500"}`}/><p className="mt-2 text-xs">Current value: {checked?"On":"Off"}</p><p className="text-xs text-[var(--ds-text-muted)]">Read-only: no supported writable Device parameter transport.</p></div>}
  else if(widget.type==="slider"&&latest)content=<div><input aria-label="Read-only Device value" type="range" disabled value={latest.value} min={widget.settings.minimum??0} max={widget.settings.maximum??100}/><p className="text-xs">Current value: {latest.value} {latest.unit??source?.unit}</p><p className="text-xs text-[var(--ds-text-muted)]">Read-only: no supported writable Device parameter transport.</p></div>;
  else if(widget.type==="metrics_over_time")content=<ChartWidget records={records} type={widget.settings.chartType}/>;
  else if(widget.type==="metric_by_devices")content=<div className="space-y-2">{fleetDevices.slice(0,10).map(item=><div key={item.id} className="flex justify-between text-xs"><span>{item.name}</span><span>{item.status}</span></div>)}</div>;
  else if(widget.type==="event_count_tile")content=<p className="text-4xl font-semibold tabular-nums">{fleetEvents.length}</p>;
  else if(widget.type==="latest_events")content=<div className="max-h-64 space-y-2 overflow-auto">{fleetEvents.slice(0,widget.settings.rowLimit??10).map(item=><div key={item.id} className="border-b border-[var(--ds-border-subtle)] pb-2 text-xs"><strong>{item.title||item.eventType}</strong><span className="block text-[var(--ds-text-muted)]">{new Date(item.occurredAt).toLocaleString()}</span></div>)}</div>;
  else if(["event_count_chart","events_by_organization","events_by_device","events_by_template"].includes(widget.type)) {const groups=new Map<string,number>();fleetEvents.forEach(item=>{const key=widget.type==="events_by_organization"?item.organization.name:widget.type==="events_by_device"?(item.device?.name||"Organization event"):widget.type==="events_by_template"?(item.device?.template?.name||"No template"):item.eventType;groups.set(key,(groups.get(key)||0)+1)});content=<div role="img" aria-label={widget.settings.title} className="space-y-2">{[...groups].slice(0,10).map(([label,count])=><div key={label} className="text-xs"><span className="flex justify-between"><span>{label}</span><strong>{count}</strong></span><span className="block h-2 rounded bg-[var(--ds-primary)]" style={{width:`${Math.max(4,count/Math.max(1,fleetEvents.length)*100)}%`}}/></div>)}</div>}
  else if(["events_over_time","events_breakdown_over_time"].includes(widget.type))content=<ChartWidget records={fleetEvents.slice().reverse().map((item,index)=>({id:item.id,deviceId:item.device?.id||"organization",key:item.eventType,value:index+1,unit:"events",recordedAt:item.occurredAt}))} type="line"/>;
  else if(widget.type==="activations")content=<ChartWidget records={activations.slice().reverse().map((item,index)=>({id:item.id,deviceId:item.device?.id||"provisioning",key:"activation",value:index+1,unit:"activations",recordedAt:item.completedAt||item.updatedAt}))} type="line"/>;
  const registered=hasWidgetRenderer(String(widget.type));
  const requiresSource=["metric","chart","gauge","table","status","device","switch","slider","metrics_over_time"].includes(widget.type) && widget.settings.sourceMode === undefined;
  return <DashboardWidget title={widget.settings.title || "Untitled widget"} loading={registered&&loading} error={registered?error:undefined} empty={!registered||widget.available===false||(requiresSource&&!source?.deviceId)||!content} emptyText={!registered?`Unsupported widget type: ${String(widget.type)}. An editor may remove it safely.`:widget.available===false?"Access unavailable. Protected widget data is no longer available.":requiresSource&&!source?.deviceId?"Widget not configured. Select an authorized Device source.":"No data returned for this widget."} editable={editable} onComment={onComment}>{content}</DashboardWidget>;
}
