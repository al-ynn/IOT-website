import api from "./api";import type {SystemSettingCategory,SystemSettingDefinition} from "../types/system-setting";
export async function getSystemSettings(){const response=await api.get<{categories:SystemSettingCategory[]}>("/admin/system-settings");return response.data.categories;}
export async function updateSystemSetting(key:string,value:number){const response=await api.patch<{data:SystemSettingDefinition}>(`/admin/system-settings/${encodeURIComponent(key)}`,{value});return response.data.data;}
export async function resetSystemSetting(key:string){const response=await api.post<{data:SystemSettingDefinition}>(`/admin/system-settings/${encodeURIComponent(key)}/reset`);return response.data.data;}
