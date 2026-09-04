export type WebhookEventType="automation.failed"|"provisioning.failed"|"provisioning.expired"|"firmware.delivery_unavailable";
export type WebhookDeliveryStatus="pending"|"retrying"|"delivered"|"failed"|"cancelled";
export interface Webhook {id:string;name:string;url:string;enabled:boolean;eventTypes:WebhookEventType[];secretPrefix:string;createdBy:{id:string;name:string}|null;lastDeliveryAt:string|null;createdAt:string;updatedAt:string}
export interface CreatedWebhook extends Webhook {secret:string}
export interface WebhookDelivery {id:string;eventId:string;eventType:string;status:WebhookDeliveryStatus;attemptCount:number;responseStatus:number|null;responseExcerpt:string|null;errorCode:string|null;errorMessage:string|null;attemptedAt:string|null;nextRetryAt:string|null;deliveredAt:string|null;createdAt:string}
