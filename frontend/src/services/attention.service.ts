import api from "./api";import type{AttentionPage,AttentionReason,AttentionState}from"../types/attention";
export const ATTENTION_REASONS:[AttentionReason,string][]=[["configuration_follow_up","Configuration Follow-up"],["operational_follow_up","Operational Follow-up"],["governance_follow_up","Governance Follow-up"],["data_quality_follow_up","Data Quality Follow-up"],["other","Other"]];
export async function getAttention(type:string,id:string){return (await api.get<{data:AttentionState|null}>(`/resources/${type}/${id}/attention`)).data.data}
export async function markAttention(type:string,id:string,reason_code:AttentionReason,note:string){await api.post(`/admin/resources/${type}/${id}/attention`,{reason_code,note})}
export async function updateAttention(type:string,id:string,reason_code:AttentionReason,note:string){await api.patch(`/admin/resources/${type}/${id}/attention`,{reason_code,note})}
export async function clearAttention(type:string,id:string){await api.post(`/admin/resources/${type}/${id}/attention/clear`)}
export async function listAttention(params:Record<string,string|number|undefined>={}){return (await api.get<AttentionPage>("/admin/needs-attention",{params})).data}
export async function listMyAttention(params:Record<string,string|number|undefined>={}){return (await api.get<AttentionPage>("/my-work/needs-attention",{params})).data}
