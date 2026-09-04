import DashboardGrid from "../layout/DashboardGrid";
import {useDashboardStore} from "../../../store/dashboard.store";
import type {DashboardWidgetDefinition,WidgetSettings} from "../../../types/dashboard";
import {Card,EmptyState} from "../../ui";

export default function DashboardCanvas({preview=false,definitions=[],onComment,timeRange}:{preview?:boolean;definitions?:DashboardWidgetDefinition[];onComment?:(id:string)=>void;timeRange?:"1h"|"6h"|"24h"|"7d"|"30d"}){
 const widgets=useDashboardStore(s=>s.widgets),updateLayout=useDashboardStore(s=>s.updateLayout),select=useDashboardStore(s=>s.selectWidget),add=useDashboardStore(s=>s.addWidget);
 const drop=(type:string)=>{const item=definitions.find(d=>d.type===type);if(!item)return;const settings={...item.defaultSettings,title:item.defaultSettings.title??item.label} as WidgetSettings;if(item.requiresDevice)settings.datasource={deviceId:"",telemetryKey:""};add({id:crypto.randomUUID(),type:item.type,settings,layout:{...item.defaultLayout,x:0,y:widgets.reduce((max,w)=>Math.max(max,w.layout.y+w.layout.h),0)}})};
 return <div className="min-h-64" onDragOver={e=>{if(!preview)e.preventDefault()}} onDrop={e=>{if(preview)return;e.preventDefault();drop(e.dataTransfer.getData("application/x-dashboard-widget"))}}>{!widgets.length?<Card><EmptyState title="Canvas is empty" description="Choose or drag a widget from the Widget Box to begin."/></Card>:<DashboardGrid widgets={widgets} definitions={definitions} editable={!preview} onLayoutChange={updateLayout} onSelect={preview?undefined:select} onComment={onComment} timeRange={timeRange}/>}</div>;
}
