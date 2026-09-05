export interface PublicationRevision{id:string;number:number}
export interface PublicationActor{id:string;name:string}
export interface ResourcePublicationVersion{id:string;number:number;current:boolean;revision:PublicationRevision;publishedBy:PublicationActor|null;publishedAt:string;previousVersionId:string|null;reviewSubmissionId:string|null;readOnly:true}
export interface PublicationVersionDetail extends ResourcePublicationVersion{snapshot:Record<string,unknown>}
export interface PublicationVersionPage{data:ResourcePublicationVersion[];current_page:number;last_page:number;per_page:number;total:number}
