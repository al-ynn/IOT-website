import api from "./api";
import { beginIdempotentSave } from "./idempotent-save";
import { deviceRevisionBase } from "./device-parameter.service";
import type {
  DeviceTemplate,
  DeviceTemplatePayload,
  DeviceParameterPayload,
} from "../types/device";
import type { ReviewHistoryPage } from "../types/review-history";
import type {
  PublicationVersionDetail,
  PublicationVersionPage,
} from "../types/publication-version";
import type { Dashboard } from "../types/dashboard";
import type { Device } from "../types/device";
export interface TemplatePage {
  data: DeviceTemplate[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
const bases = new Map<string, string>();
export async function listDeviceTemplates(search = "", page = 1) {
  const r = await api.get<TemplatePage>("/device-templates", {
    params: { search: search || undefined, page },
  });
  return r.data;
}
export async function getDeviceTemplate(id: string) {
  const [r, state] = await Promise.all([
    api.get<{ data: DeviceTemplate }>(`/device-templates/${id}`),
    api
      .get<{ latestRevision: { id: string } }>(
        `/collaboration/resources/device_template/${id}/revision-state`,
      )
      .catch(() => null),
  ]);
  if (state) bases.set(id, state.data.latestRevision.id);
  return r.data.data;
}
export async function createDeviceTemplate(payload: DeviceTemplatePayload) {
  const r = await api.post<{ data: DeviceTemplate }>(
    "/device-templates",
    payload,
  );
  return r.data.data;
}
export async function updateDeviceTemplate(
  id: string,
  payload: Partial<DeviceTemplatePayload>,
) {
  const command = { ...payload, baseRevisionId: bases.get(id) };
  const attempt = beginIdempotentSave(`template:${id}`, command);
  const r = await api.patch<{ data: DeviceTemplate }>(
    `/device-templates/${id}`,
    command,
    { headers: { "Idempotency-Key": attempt.key } },
  );
  attempt.complete();
  return r.data.data;
}
export async function deleteDeviceTemplate(id: string) {
  await api.delete(`/device-templates/${id}`);
}
export async function duplicateDeviceTemplate(id: string) {
  const r = await api.post<{ data: DeviceTemplate }>(
    `/device-templates/${id}/duplicate`,
  );
  return r.data.data;
}
export async function addTemplateParameter(
  id: string,
  payload: DeviceParameterPayload,
) {
  const command = { ...payload, baseRevisionId: bases.get(id) };
  const attempt = beginIdempotentSave(
    `template:${id}:parameter:create`,
    command,
  );
  const r = await api.post<{ data: DeviceTemplate }>(
    `/device-templates/${id}/parameters`,
    command,
    { headers: { "Idempotency-Key": attempt.key } },
  );
  attempt.complete();
  return r.data.data;
}
export async function updateTemplateParameter(
  id: string,
  parameterId: string,
  payload: Partial<DeviceParameterPayload>,
) {
  const command = { ...payload, baseRevisionId: bases.get(id) };
  const attempt = beginIdempotentSave(
    `template:${id}:parameter:${parameterId}`,
    command,
  );
  const r = await api.patch<{ data: DeviceTemplate }>(
    `/device-templates/${id}/parameters/${parameterId}`,
    command,
    { headers: { "Idempotency-Key": attempt.key } },
  );
  attempt.complete();
  return r.data.data;
}
export async function deleteTemplateParameter(id: string, parameterId: string) {
  const command = { baseRevisionId: bases.get(id) };
  const attempt = beginIdempotentSave(
    `template:${id}:parameter:${parameterId}:delete`,
    command,
  );
  await api.delete(`/device-templates/${id}/parameters/${parameterId}`, {
    data: command,
    headers: { "Idempotency-Key": attempt.key },
  });
  attempt.complete();
}
export async function applyDeviceTemplate(
  deviceId: string,
  templateId: string,
  admin = false,
) {
  const command = {
    template_id: templateId,
    baseRevisionId: deviceRevisionBase(deviceId),
  };
  const attempt = beginIdempotentSave(`device:${deviceId}:template`, command);
  await api.post(
    `${admin ? "/admin" : ""}/devices/${deviceId}/apply-template`,
    command,
    { headers: { "Idempotency-Key": attempt.key } },
  );
  attempt.complete();
}
export async function getTemplateReviewHistory(id: string, page = 1) {
  const r = await api.get<ReviewHistoryPage>(
    `/device-templates/${id}/review-history`,
    { params: { page } },
  );
  return r.data;
}
export async function getTemplatePublicationVersions(id: string, page = 1) {
  const r = await api.get<PublicationVersionPage>(
    `/device-templates/${id}/publication/versions`,
    { params: { page } },
  );
  return r.data;
}
export async function getTemplatePublicationVersion(
  id: string,
  versionId: string,
) {
  const r = await api.get<{ data: PublicationVersionDetail }>(
    `/device-templates/${id}/publication/versions/${versionId}`,
  );
  return r.data.data;
}
export async function getTemplateDashboard(id:string){return (await api.get<Dashboard>(`/device-templates/${id}/dashboard`)).data;}
export async function updateTemplateDashboard(id:string,dashboard:Dashboard){const attempt=beginIdempotentSave(`template:${id}:dashboard`,dashboard);const response=await api.patch<Dashboard>(`/device-templates/${id}/dashboard`,dashboard,{headers:{"Idempotency-Key":attempt.key}});attempt.complete();return response.data;}
export async function createTemplateDevice(id:string,name:string){return (await api.post<Device>(`/device-templates/${id}/devices`,{name})).data;}
