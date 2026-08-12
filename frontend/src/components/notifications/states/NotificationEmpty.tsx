import { Bell } from "lucide-react";import { EmptyState } from "../../ui";
export default function NotificationEmpty(){return <EmptyState icon={<Bell size={18}/>} title="No notifications" description="There are no notifications matching the selected filters."/>}
