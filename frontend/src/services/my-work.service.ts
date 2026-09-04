import api from "./api";
import type{MyWorkPage,MyWorkRelationship,MyWorkResourceType}from"../types/my-work";
export async function listMyWork(params:{resource_type?:MyWorkResourceType;lifecycle?:string;relationship?:MyWorkRelationship;q?:string;page?:number;per_page?:number}={}):Promise<MyWorkPage>{return(await api.get<MyWorkPage>("/my-work",{params})).data}
