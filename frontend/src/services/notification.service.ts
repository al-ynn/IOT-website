/** Notification inbox endpoints are not currently exposed by Laravel. */
export const notificationCapabilities = {
  list: false,
  unreadCount: false,
  markRead: false,
} as const;
