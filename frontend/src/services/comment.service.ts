import api from "./api";
import type {
  CollaborationComment,
  CollaborationThread,
  ThreadList,
} from "../types/comment";
export interface ThreadAnchor {
  type:
    | "resource"
    | "section"
    | "dashboard_widget"
    | "parameter"
    | "metadata"
    | "name"
    | "description";
  key?: string | null;
  originRevisionId?: string | null;
}
export async function listThreads(
  type: string,
  id: string,
  status?: "open" | "resolved",
  anchor?: ThreadAnchor,
) {
  return (
    await api.get<ThreadList>(`/collaboration/${type}/${id}/threads`, {
      params: { status, anchor_type: anchor?.type, anchor_key: anchor?.key },
    })
  ).data;
}
export async function createThread(
  type: string,
  id: string,
  body: string,
  anchor?: ThreadAnchor,
) {
  return (
    await api.post<CollaborationThread>(
      `/collaboration/${type}/${id}/threads`,
      {
        body,
        anchor_type: anchor?.type ?? "resource",
        anchor_key: anchor?.key ?? null,
        origin_revision_id: anchor?.originRevisionId ?? null,
      },
    )
  ).data;
}
export async function replyThread(id: string, body: string) {
  return (
    await api.post<CollaborationComment>(
      `/collaboration/threads/${id}/comments`,
      { body },
    )
  ).data;
}
export async function acknowledgeThread(id: string) {
  return (
    await api.post<CollaborationThread>(
      `/collaboration/threads/${id}/acknowledge`,
    )
  ).data;
}
export async function resolveThread(id: string) {
  return (
    await api.post<CollaborationThread>(`/collaboration/threads/${id}/resolve`)
  ).data;
}
export async function reopenThread(id: string) {
  return (
    await api.post<CollaborationThread>(`/collaboration/threads/${id}/reopen`)
  ).data;
}
