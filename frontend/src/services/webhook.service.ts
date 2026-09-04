import api from "./api";
import { beginIdempotentSave } from "./idempotent-save";
import type {
  CreatedWebhook,
  Webhook,
  WebhookDelivery,
  WebhookEventType,
} from "../types/webhook";
export async function listWebhooks() {
  return (await api.get<{ data: Webhook[] }>("/webhooks")).data.data;
}
export async function getWebhook(id: string) {
  return (await api.get<{ data: Webhook }>(`/webhooks/${id}`)).data.data;
}
export async function createWebhook(payload: {
  name: string;
  url: string;
  event_types: WebhookEventType[];
}) {
  return (await api.post<{ data: CreatedWebhook }>("/webhooks", payload)).data
    .data;
}
export async function updateWebhook(
  id: string,
  payload: Partial<{
    name: string;
    url: string;
    enabled: boolean;
    event_types: WebhookEventType[];
    baseRevisionId: string | null;
  }>,
) {
  const attempt = beginIdempotentSave(`webhook:${id}`, payload);
  const result = (
    await api.patch<{ data: Webhook }>(`/webhooks/${id}`, payload, {
      headers: { "Idempotency-Key": attempt.key },
    })
  ).data.data;
  attempt.complete();
  return result;
}
export async function deleteWebhook(id: string) {
  await api.delete(`/webhooks/${id}`);
}
export async function rotateWebhookSecret(id: string) {
  return (
    await api.post<{ data: CreatedWebhook }>(`/webhooks/${id}/rotate-secret`)
  ).data.data;
}
export async function sendWebhookTest(id: string) {
  return (
    await api.post<{ data: { deliveryId: string; status: string } }>(
      `/webhooks/${id}/test`,
    )
  ).data.data;
}
export async function listWebhookDeliveries(id: string) {
  return (
    await api.get<{ data: WebhookDelivery[] }>(`/webhooks/${id}/deliveries`)
  ).data.data;
}
export async function retryWebhookDelivery(id: string, deliveryId: string) {
  await api.post(`/webhooks/${id}/deliveries/${deliveryId}/retry`);
}
