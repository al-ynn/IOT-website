import api from "./api";
import type { AutomationDefinition,AutomationExecution,AutomationRule } from "../types/automation";
export async function getAutomations(){return (await api.get<AutomationRule[]>("/automations")).data;}
export async function getAutomation(id:string){return (await api.get<AutomationRule>(`/automations/${id}`)).data;}
export async function createAutomation(rule:AutomationDefinition){return (await api.post<AutomationRule>("/automations",rule)).data;}
export async function updateAutomation(id:string,data:Partial<AutomationDefinition>){return (await api.put<AutomationRule>(`/automations/${id}`,data)).data;}
export async function deleteAutomation(id:string){await api.delete(`/automations/${id}`);}
export async function enableAutomation(id:string){return (await api.post<AutomationRule>(`/automations/${id}/enable`)).data;}
export async function disableAutomation(id:string){return (await api.post<AutomationRule>(`/automations/${id}/disable`)).data;}
export async function executeAutomation(id:string,data:Record<string,unknown>={}){return (await api.post<AutomationExecution>(`/automations/${id}/execute`,{data})).data;}
export async function getAutomationExecutions(id:string){return (await api.get<{data:AutomationExecution[]}>(`/automations/${id}/executions`)).data.data;}
