import api from "./api";
import type { ResourceLifecycle } from "../types/lifecycle";
export async function getLifecycle(type: string, id: string) {
  return (
    await api.get<{ data: ResourceLifecycle }>(
      `/resources/${type}/${id}/lifecycle`,
    )
  ).data.data;
}
async function transition(
  type: string,
  id: string,
  action: "disable" | "restore" | "archive",
  current?: ResourceLifecycle,
) {
  return (
    await api.post<{ data: ResourceLifecycle }>(
      `/admin/resources/${type}/${id}/lifecycle/${action}`,
      current
        ? {
            expectedLifecycle: current.state,
            lifecycleGeneration: current.lifecycleGeneration,
          }
        : {},
    )
  ).data.data;
}
export const disableResource = (
  type: string,
  id: string,
  current?: ResourceLifecycle,
) => transition(type, id, "disable", current);
export const restoreResource = (
  type: string,
  id: string,
  current?: ResourceLifecycle,
) => transition(type, id, "restore", current);
export const archiveResource = (
  type: string,
  id: string,
  current?: ResourceLifecycle,
) => transition(type, id, "archive", current);
