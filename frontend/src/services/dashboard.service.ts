import api from "./api";
import { beginIdempotentSave } from "./idempotent-save";

import type { Dashboard } from "../types/dashboard";

export async function getDashboards() {
  const response = await api.get<Dashboard[]>("/dashboards");

  return response.data;
}

export async function createDashboard(dashboard: Dashboard) {
  const response = await api.post(
    "/dashboards",

    dashboard,
  );

  return response.data;
}

export async function getDashboard(id: string) {
  const response = await api.get<Dashboard>(`/dashboards/${id}`);

  return response.data;
}

export async function updateDashboard(
  id: string,

  dashboard: Dashboard,
) {
  const attempt = beginIdempotentSave(`dashboard:${id}`, dashboard);
  const response = await api.put(`/dashboards/${id}`, dashboard, {
    headers: { "Idempotency-Key": attempt.key },
  });
  attempt.complete();

  return response.data;
}

export async function deleteDashboard(id: string) {
  return api.delete(`/dashboards/${id}`);
}

export async function duplicateDashboard(id: string) {
  const response = await api.post(`/dashboards/${id}/duplicate`);

  return response.data;
}

export async function getDefaultDashboard() {
  const response = await api.get<Dashboard>("/dashboards/default");
  return response.data;
}

export async function uploadDashboardMapAsset(dashboardId: string, file: File) {
  const body = new FormData();
  body.append("asset", file);
  const response = await api.post(`/dashboards/${dashboardId}/map-assets`, body, { headers: { "Content-Type": "multipart/form-data" } });
  return response.data.data as { id: string; mimeType: string; size: number; width: number; height: number; url: string };
}

export function dashboardMapAssetUrl(assetId: string | number): string {
  const value = String(assetId);
  return value.startsWith("/") || value.startsWith("http") ? value : `/api/dashboard-map-assets/${value}`;
}
export async function getGlobalDashboard() {
  const response = await api.get<Dashboard>("/admin/dashboards/global");
  return response.data;
}
export async function updateGlobalDashboard(dashboard: Dashboard) {
  const response = await api.put<Dashboard>(
    "/admin/dashboards/global",
    dashboard,
  );
  return response.data;
}
export async function getDeviceDashboard(deviceId: string, admin = false) {
  const response = await api.get<Dashboard>(
    admin
      ? `/admin/devices/${deviceId}/dashboard`
      : `/devices/${deviceId}/dashboard`,
  );
  return response.data;
}
export async function updateDeviceDashboard(
  deviceId: string,
  dashboard: Dashboard,
  admin = false,
) {
  const attempt = beginIdempotentSave(
    `device:${deviceId}:dashboard`,
    dashboard,
  );
  const response = await api.patch<Dashboard>(
    admin
      ? `/admin/devices/${deviceId}/dashboard`
      : `/devices/${deviceId}/dashboard`,
    dashboard,
    { headers: { "Idempotency-Key": attempt.key } },
  );
  attempt.complete();
  return response.data;
}
