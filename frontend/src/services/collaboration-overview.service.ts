import api from"./api";import type{CollaborationOverview}from"../types/collaboration-overview";
export async function getCollaborationOverview():Promise<CollaborationOverview>{return(await api.get<CollaborationOverview>("/collaboration-overview")).data}
