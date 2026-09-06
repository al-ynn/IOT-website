import api from "./api";
import { beginIdempotentSave } from "./idempotent-save";
import type {
  FirmwareArtifact,
  FirmwareDeployment,
  FirmwareReleaseState,
  Page,
} from "../types/firmware";
export async function listFirmwareArtifacts(search = "") {
  return (
    await api.get<Page<FirmwareArtifact>>("/firmware/artifacts", {
      params: { search: search || undefined },
    })
  ).data;
}
export async function uploadFirmware(form: FormData) {
  return (
    await api.post<{ data: FirmwareArtifact }>("/firmware/artifacts", form, {
      headers: { "Content-Type": "multipart/form-data" },
    })
  ).data.data;
}
export async function updateFirmware(
  id: string,
  payload: {
    name?: string;
    description?: string | null;
    baseRevisionId?: string | null;
  },
) {
  const attempt = beginIdempotentSave(`firmware:${id}`, payload);
  const result = (
    await api.patch<{ data: FirmwareArtifact }>(
      `/firmware/artifacts/${id}`,
      payload,
      { headers: { "Idempotency-Key": attempt.key } },
    )
  ).data.data;
  attempt.complete();
  return result;
}
export async function deleteFirmware(id: string) {
  await api.delete(`/firmware/artifacts/${id}`);
}
export async function downloadFirmware(id: string, name: string) {
  const response = await api.get<Blob>(`/firmware/artifacts/${id}/download`, {
    responseType: "blob",
  });
  const url = URL.createObjectURL(response.data);
  const link = document.createElement("a");
  link.href = url;
  link.download = name;
  link.click();
  URL.revokeObjectURL(url);
}
export async function listFirmwareDeployments() {
  return (await api.get<Page<FirmwareDeployment>>("/firmware/deployments"))
    .data;
}
export async function createFirmwareDeployment(
  artifactId: string,
  deviceIds: string[],
) {
  return (
    await api.post<{ data: FirmwareDeployment }>("/firmware/deployments", {
      firmware_artifact_id: artifactId,
      device_ids: deviceIds,
    })
  ).data.data;
}
export async function submitFirmwareRelease(id: string) {
  return (await api.post(`/firmware/artifacts/${id}/release-submissions`)).data;
}
export async function getFirmwareReleaseState(id: string) {
  return (
    await api.get<{ data: FirmwareReleaseState }>(
      `/firmware/artifacts/${id}/release`,
    )
  ).data.data;
}
