import ReportHeader from "../../components/reports/layout/ReportHeader";import ReportBuilder from "../../components/reports/builder/ReportBuilder";
export default function CreateReport(){return <div className="mx-auto max-w-5xl space-y-5"><ReportHeader title="Create report" description="Configure a backend-generated report."/><ReportBuilder/></div>}
