import api from "./api";import type{ResourceDraft}from"../types/draft";
const base=(type:string,id:string)=>`/collaboration/resources/${type}/${id}/draft`;
export async function getDraft(type:string,id:string){return(await api.get<{draft:ResourceDraft|null}>(base(type,id))).data.draft}
export async function saveDraft(type:string,id:string,baseRevisionId:string,snapshot:Record<string,unknown>){return(await api.put<ResourceDraft>(base(type,id),{base_revision_id:baseRevisionId,snapshot})).data}
export async function discardDraft(type:string,id:string){await api.delete(base(type,id))}
export async function applyDraft(type:string,id:string){return(await api.post<{resourceType:string;resourceId:string;draftCleared:boolean;latestRevisionId:string}>(`${base(type,id)}/apply`)).data}
