import api from "./api";
import { beginIdempotentSave } from "./idempotent-save";
import type { DeviceParameter, DeviceParameterPayload } from "../types/device";
import type { ResourceRevisionState } from "../types/pull-update";
const bases = new Map<string, string>();
const root = (id: string, admin = false) =>
  admin ? `/admin/devices/${id}/parameters` : `/devices/${id}/parameters`;
export async function listDeviceParameters(id: string, admin = false) {
  const [r, state] = await Promise.all([
    api.get<{ data: DeviceParameter[] }>(root(id, admin)),
    api
      .get<ResourceRevisionState>(
        `/collaboration/resources/device/${id}/revision-state`,
      )
      .catch(() => null),
  ]);
  if (state) bases.set(id, state.data.latestRevision.id);
  return r.data.data;
}
export async function createDeviceParameter(
  id: string,
  payload: DeviceParameterPayload,
  admin = false,
) {
  const command = { ...payload, baseRevisionId: bases.get(id) };
  const attempt = beginIdempotentSave(`device:${id}:parameter:create`, command);
  const r = await api.post<{ data: DeviceParameter }>(
    root(id, admin),
    command,
    { headers: { "Idempotency-Key": attempt.key } },
  );
  attempt.complete();
  return r.data.data;
}
export async function updateDeviceParameter(
  id: string,
  parameterId: string,
  payload: Partial<
    Pick<DeviceParameterPayload, "name" | "unit" | "description">
  >,
  admin = false,
) {
  const command = { ...payload, baseRevisionId: bases.get(id) };
  const attempt = beginIdempotentSave(
    `device:${id}:parameter:${parameterId}`,
    command,
  );
  const r = await api.patch<{ data: DeviceParameter }>(
    `${root(id, admin)}/${parameterId}`,
    command,
    { headers: { "Idempotency-Key": attempt.key } },
  );
  attempt.complete();
  return r.data.data;
}
export async function deleteDeviceParameter(
  id: string,
  parameterId: string,
  admin = false,
) {
  const command = { baseRevisionId: bases.get(id) };
  const attempt = beginIdempotentSave(
    `device:${id}:parameter:${parameterId}:delete`,
    command,
  );
  await api.delete(`${root(id, admin)}/${parameterId}`, {
    data: command,
    headers: { "Idempotency-Key": attempt.key },
  });
  attempt.complete();
}
export function deviceRevisionBase(id: string) {
  return bases.get(id);
}
