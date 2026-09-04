import api from "./api";import type {AppNotification,NotificationCategory,NotificationListResponse,NotificationStateFilter} from "../types/notification";
export async function listNotifications(params?:{filter?:NotificationStateFilter;category?:NotificationCategory;page?:number;per_page?:10|20|50}){const response=await api.get<NotificationListResponse>("/notifications",{params});return response.data}
export async function recentNotifications(){const response=await api.get<{data:AppNotification[]}>("/notifications/recent");return response.data.data}
export async function unreadNotificationCount(){const response=await api.get<{count:number}>("/notifications/unread-count");return response.data.count}
export async function markNotificationRead(id:string){const response=await api.post<{data:AppNotification}>(`/notifications/${id}/read`);return response.data.data}
export async function markNotificationUnread(id:string){const response=await api.post<{data:AppNotification}>(`/notifications/${id}/unread`);return response.data.data}
export async function markAllNotificationsRead(){const response=await api.post<{updated:number}>("/notifications/read-all");return response.data.updated}
export async function dismissNotification(id:string){await api.post(`/notifications/${id}/dismiss`)}
export async function restoreNotification(id:string){const response=await api.post<{data:AppNotification}>(`/notifications/${id}/restore`);return response.data.data}
