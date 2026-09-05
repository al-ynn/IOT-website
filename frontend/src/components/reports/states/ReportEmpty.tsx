import {FileText} from "lucide-react";import {EmptyState} from "../../ui";
export default function ReportEmpty(){return <EmptyState icon={<FileText size={18}/>} title="No reports" description="No reports match the current filters."/>}
