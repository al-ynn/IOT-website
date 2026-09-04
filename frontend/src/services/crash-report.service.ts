import api from "./api";import type {CrashReport,CrashReportFilters,CrashReportListResponse} from "../types/crash-report";
export async function listCrashReports(filters:CrashReportFilters={}){return(await api.get<CrashReportListResponse>("/crash-reports",{params:filters})).data}
export async function getCrashReport(id:string){return(await api.get<{data:CrashReport}>(`/crash-reports/${id}`)).data.data}
export async function listAdminCrashReports(filters:CrashReportFilters={}){return(await api.get<CrashReportListResponse>("/admin/crash-reports",{params:filters})).data}
export async function getAdminCrashReport(id:string){return(await api.get<{data:CrashReport}>(`/admin/crash-reports/${id}`)).data.data}
