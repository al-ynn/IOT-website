import api from "./api";import type {ProvisioningCompletionPayload,ProvisioningFilters,ProvisioningListResponse,ProvisioningSession} from "../types/provisioning";
export async function listProvisioningSessions(filters:ProvisioningFilters={}){return(await api.get<ProvisioningListResponse>("/provisioning-sessions",{params:filters})).data;}
export async function getProvisioningSession(id:string){return(await api.get<{data:ProvisioningSession}>(`/provisioning-sessions/${id}`)).data.data;}
export async function createProvisioningSession(payload:{name?:string|null;device_template_id?:string|null}){return(await api.post<{data:ProvisioningSession}>("/provisioning-sessions",payload)).data.data;}
export async function cancelProvisioningSession(id:string){return(await api.post<{data:ProvisioningSession}>(`/provisioning-sessions/${id}/cancel`)).data.data;}
export async function failProvisioningSession(id:string,payload:{failure_code:string;failure_message:string}){return(await api.post<{data:ProvisioningSession}>(`/provisioning-sessions/${id}/fail`,payload)).data.data;}
export async function completeProvisioningSession(id:string,payload:ProvisioningCompletionPayload){return(await api.post<{data:ProvisioningSession}>(`/provisioning-sessions/${id}/complete`,payload)).data.data;}
