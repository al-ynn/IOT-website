import api from "./api";
import { beginIdempotentSave } from "./idempotent-save";
import type { Location, LocationPage } from "../types/location";
export async function listLocations(search = "") {
  return (
    await api.get<LocationPage>("/locations", {
      params: { search, per_page: 100 },
    })
  ).data;
}
export async function listAdminLocations(search = "", organizationId?: string) {
  return (
    await api.get<LocationPage>("/admin/locations", {
      params: {
        search,
        organization_id: organizationId || undefined,
        per_page: 100,
      },
    })
  ).data;
}
export async function createLocation(payload: {
  organization_id: string;
  name: string;
  description?: string | null;
}) {
  return (await api.post<{ data: Location }>("/admin/locations", payload)).data
    .data;
}
export async function createStaffLocation(payload: {
  name: string;
  description?: string | null;
}) {
  return (await api.post<{ data: Location }>("/locations", payload)).data.data;
}
async function saveLocation(
  path: string,
  id: string,
  payload: {
    name?: string;
    description?: string | null;
    baseRevisionId?: string | null;
  },
) {
  const attempt = beginIdempotentSave(`location:${id}`, payload);
  const result = (
    await api.patch<{ data: Location }>(path, payload, {
      headers: { "Idempotency-Key": attempt.key },
    })
  ).data.data;
  attempt.complete();
  return result;
}
export async function updateLocation(
  id: string,
  payload: {
    name?: string;
    description?: string | null;
    baseRevisionId?: string | null;
  },
) {
  return saveLocation(`/admin/locations/${id}`, id, payload);
}
export async function updateStaffLocation(
  id: string,
  payload: {
    name?: string;
    description?: string | null;
    baseRevisionId?: string | null;
  },
) {
  return saveLocation(`/locations/${id}`, id, payload);
}
export async function getLocation(id: string, admin = false) {
  return (
    await api.get<{ data: Location }>(
      `${admin ? "/admin" : ""}/locations/${id}`,
    )
  ).data.data;
}
export async function listLocationDevices(id: string, admin = false) {
  return (
    await api.get<{
      data: Array<{
        id: string;
        name: string;
        external_id: string | null;
        status: string;
      }>;
    }>(`${admin ? "/admin" : ""}/locations/${id}/devices`)
  ).data;
}
