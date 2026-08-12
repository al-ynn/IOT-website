import AdminPageHeader from "../../components/admin/layout/AdminPageHeader";import SystemStatus from "../../components/admin/system/SystemStatus";
export default function AdminSystem(){return <div className="space-y-5"><AdminPageHeader title="System" description="Internal application and infrastructure health."/><SystemStatus/></div>}
