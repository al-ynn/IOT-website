import api from "./api";
import type { AutomationDefinition,AutomationExecution,AutomationExecutionPage,AutomationPage,AutomationRule,TriggerType } from "../types/automation";
export interface AutomationFilters{search?:string;status?:"enabled"|"disabled";trigger_type?:TriggerType;device_id?:string;page?:number;per_page?:25|50|100}
export async function getAutomations(filters:AutomationFilters={}){return (await api.get<AutomationPage>("/automations",{params:filters})).data;}
export async function getAutomation(id:string){return (await api.get<AutomationRule>(`/automations/${id}`)).data;}
export async function createAutomation(rule:AutomationDefinition){return (await api.post<AutomationRule>("/automations",rule)).data;}
export async function updateAutomation(id:string,data:Partial<AutomationDefinition>,baseRevisionId:string|null|undefined,idempotencyKey:string){return (await api.put<AutomationRule>(`/automations/${id}`,{...data,baseRevisionId},{headers:{"Idempotency-Key":idempotencyKey}})).data;}
export async function deleteAutomation(id:string){await api.delete(`/automations/${id}`);}
export async function enableAutomation(id:string){return (await api.post<AutomationRule>(`/automations/${id}/enable`)).data;}
export async function disableAutomation(id:string){return (await api.post<AutomationRule>(`/automations/${id}/disable`)).data;}
export async function executeAutomation(id:string,data:Record<string,unknown>={}){return (await api.post<AutomationExecution>(`/automations/${id}/execute`,{data})).data;}
export async function getAutomationExecutions(id:string,page=1){return (await api.get<AutomationExecutionPage>(`/automations/${id}/executions`,{params:{page}})).data;}
