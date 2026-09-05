import type {ReactNode} from "react";
import {Link,useSearchParams} from "react-router-dom";
import {resolveResourceTabs} from "../../navigation/resource-tabs";
import type {Device} from "../../types/device";
import AttentionBanner from "../attention/AttentionBanner";
import CommentsPanel from "../collaboration/CommentsPanel";
import RevisionHistoryPanel from "../collaboration/RevisionHistoryPanel";
import CrashReportsPanel from "./CrashReportsPanel";
import DeviceDashboardPanel from "./DeviceDashboardPanel";
import DeviceSnapshotsPanel from "./DeviceSnapshotsPanel";
import DeviceConfiguration from "./configuration/DeviceConfiguration";
import DeviceHeader from "./layout/DeviceHeader";
import DeviceTabs from "./layout/DeviceTabs";
import DeviceParametersPanel from "./parameters/DeviceParametersPanel";
import DeviceCredentials from "./security/DeviceCredentials";
import DeviceAnalyticsPanel from "./telemetry/DeviceAnalyticsPanel";
import DeviceTelemetryPanel from "./telemetry/DeviceTelemetryPanel";
import DeviceOverview from "./tabs/DeviceOverview";
import DeviceMetadataPanel from "./tabs/DeviceMetadataPanel";
import DeviceEventsPanel from "./tabs/DeviceEventsPanel";

export interface DeviceWorkspaceCapabilities{canEdit:boolean;canShare:boolean;canEditDashboard:boolean;canEditParameters:boolean;canManageCredentials:boolean;canManageAccess:boolean;canViewAnalytics:boolean;canViewAdminMetadata:boolean}
export default function DeviceWorkspace({device,capabilities,routeContext="normal",onDelete,onUpdated,adminMetadata}:{device:Device;capabilities:DeviceWorkspaceCapabilities;routeContext?:"normal"|"admin_global";onDelete?:()=>void;onUpdated?:(device:Device)=>void;adminMetadata?:ReactNode}){
 const [params]=useSearchParams();const id=device.id!;const admin=routeContext==="admin_global";const basePath=admin?`/admin/devices/${id}`:`/app/devices/${id}`;
 const tabCapabilities={edit:capabilities.canEdit,"dashboard.edit":capabilities.canEditDashboard,"parameters.edit":capabilities.canEditParameters,"credentials.manage":capabilities.canManageCredentials,"analytics.view":capabilities.canViewAnalytics};
 const tab=resolveResourceTabs("device",params.get("tab"),{admin,capabilities:tabCapabilities}).activeKey;
 let content:ReactNode;
 if(tab==="comments")content=<CommentsPanel resourceId={id} canResolve={capabilities.canEdit}/>;
 else if(tab==="revisions")content=<RevisionHistoryPanel resourceId={id}/>;
 else if(tab==="dashboard")content=<DeviceDashboardPanel deviceId={id} admin={admin} canEdit={capabilities.canEditDashboard}/>;
 else if(tab==="parameters")content=<DeviceParametersPanel deviceId={id} admin={admin} canManage={capabilities.canEditParameters}/>;
 else if(tab==="metadata")content=<DeviceMetadataPanel deviceId={id} canEdit={capabilities.canEdit}/>;
 else if(tab==="events")content=<DeviceEventsPanel deviceId={id}/>;
 else if(tab==="credentials")content=<DeviceCredentials device={device} admin={admin} canManage={capabilities.canManageCredentials}/>;
 else if(tab==="crashes")content=<CrashReportsPanel deviceId={id} admin={admin}/>;
 else if(tab==="telemetry")content=<DeviceTelemetryPanel deviceId={id}/>;
 else if(tab==="analytics")content=<DeviceAnalyticsPanel deviceId={id}/>;
 else if(tab==="configuration")content=onUpdated?<DeviceConfiguration device={device} canManage={capabilities.canEdit} onUpdated={onUpdated}/>:null;
 else if(tab==="snapshots")content=<DeviceSnapshotsPanel deviceId={id} canManage={capabilities.canEdit}/>;
 else content=<><DeviceOverview device={device} loadAnalytics={!admin}/>{adminMetadata}</>;
 return <div className="space-y-5"><DeviceHeader device={device} canManage={capabilities.canEdit&&!admin} onDelete={onDelete??(()=>undefined)}/><AttentionBanner type="device" id={id} admin={admin}/>{capabilities.canShare&&!admin&&<div className="flex justify-end"><Link className="inline-flex h-8 items-center rounded-md border border-[var(--ds-border)] px-3 text-xs" to={`${basePath}/share`}>Share Device</Link></div>}<DeviceTabs basePath={basePath} admin={admin} requestedTab={params.get("tab")} capabilities={tabCapabilities}/><section aria-label={`${tab} device section`}>{content}</section></div>;
}
