import api from"./api";
export interface DashboardReviewSubmission{id:string;status:string;reviewState:"available"|"in_review"|"terminal";reviewer:{id:string;name:string}|null;submittedRevision:{id:string;number:number};submittedBy:{id:string;name:string}|null;newerPrivateRevisionExists:boolean;decisionNote:string|null;publicationVersion:{id:string;number:number}|null;reviewSnapshot:{metadata:{name?:string;description?:string};dashboard:{widgets:Array<{id:string;type:string;title?:string;layout:{x:number;y:number;w:number;h:number};configuration:Record<string,unknown>}>}}}
const base=(id:string)=>`/admin/dashboard-publication-submissions/${id}`;
export async function getDashboardReview(id:string){return(await api.get<{data:DashboardReviewSubmission}>(base(id))).data.data}
export async function claimDashboardReview(id:string){return(await api.post<{data:DashboardReviewSubmission}>(`${base(id)}/claim`)).data.data}
export async function releaseDashboardReview(id:string){return(await api.post(`${base(id)}/release`)).data}
export async function takeoverDashboardReview(id:string){return(await api.post(`${base(id)}/takeover`,{confirm:true})).data}
export async function approveDashboardReview(id:string,confirmOlder:boolean){return(await api.post(`${base(id)}/approve`,{confirm_older_revision:confirmOlder})).data}
export async function requestDashboardReviewChanges(id:string,note:string){return(await api.post(`${base(id)}/request-changes`,{note})).data}
export async function rejectDashboardReview(id:string,note:string){return(await api.post(`${base(id)}/reject`,{note})).data}