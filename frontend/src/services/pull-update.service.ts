import api from "./api";
import type {
  ChangesPage,
  ResourceRevisionState,
  RevisionRangeSummary,
  RevisionReminderPreset,
  RevisionReminderState,
} from "../types/pull-update";
const base = (type: string, id: string) =>
  `/collaboration/resources/${type}/${id}`;
export const pullUpdateService = {
  state: async (type: string, id: string) =>
    (await api.get<ResourceRevisionState>(`${base(type, id)}/revision-state`))
      .data,
  review: async (type: string, id: string) =>
    (await api.get<RevisionRangeSummary>(`${base(type, id)}/review-changes`))
      .data,
  pull: async (type: string, id: string) =>
    (await api.post<ResourceRevisionState>(`${base(type, id)}/pull`)).data,
  ignore: async (type: string, id: string) =>
    (await api.post<ResourceRevisionState>(`${base(type, id)}/ignore`)).data,
  changes: async (page = 1, q = "") =>
    (
      await api.get<ChangesPage>("/changes", {
        params: { page, q: q || undefined },
      })
    ).data,
  reminder: async (type: string, id: string) =>
    (await api.get<RevisionReminderState>(`${base(type, id)}/reminder`)).data,
  scheduleReminder: async (
    type: string,
    id: string,
    preset: RevisionReminderPreset,
  ) =>
    (
      await api.post<RevisionReminderState>(`${base(type, id)}/reminder`, {
        preset,
      })
    ).data,
  cancelReminder: async (type: string, id: string) =>
    (await api.delete<RevisionReminderState>(`${base(type, id)}/reminder`))
      .data,
};
