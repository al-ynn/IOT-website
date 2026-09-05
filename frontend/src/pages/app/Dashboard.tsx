import {useCallback} from "react";
import DashboardWorkspace from "../../components/dashboard/DashboardWorkspace";
import AdminMonitoredDashboardPage from "./AdminMonitoredDashboard";
import {useAuth} from "../../hooks/useAuth";
import {getDefaultDashboard,updateDashboard} from "../../services/dashboard.service";

export default function Dashboard(){const {user}=useAuth();const load=useCallback(()=>getDefaultDashboard(),[]);const save=useCallback((dashboard:Awaited<ReturnType<typeof getDefaultDashboard>>)=>updateDashboard(dashboard.id!,dashboard),[]);return <DashboardWorkspace load={load} save={save} fallback={user?.platformRole==="platform_admin"?<AdminMonitoredDashboardPage/>:undefined}/>}
