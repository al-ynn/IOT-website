import api from './api';
export interface EditingPresenceEditor{sessionId:string|null;user:{id:string;displayName:string;inactive:boolean};sameUser:boolean;startedAt:string;expiresAt:string}
export interface EditingLeasePayload{lease:{token:string;expiresAt:string;ttlSeconds:number};editors:EditingPresenceEditor[]}
const root=(type:string,id:string)=>`/resources/${encodeURIComponent(type)}/${encodeURIComponent(id)}/editing-sessions`;
export async function startEditingLease(type:string,id:string){return(await api.post<{data:EditingLeasePayload}>(root(type,id))).data.data}
export async function heartbeatEditingLease(type:string,id:string,token:string){return(await api.post<{data:EditingLeasePayload}>(`${root(type,id)}/${token}/heartbeat`)).data.data}
export async function listEditingLeases(type:string,id:string){return(await api.get<{data:EditingPresenceEditor[]}>(root(type,id))).data.data}
export async function releaseEditingLease(type:string,id:string,token:string){await api.delete(`${root(type,id)}/${token}`)}
