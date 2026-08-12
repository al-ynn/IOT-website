# Notification and activity migration

Phase 24 removes the static sample dashboard activity feed and replaces the header's generic empty popover with routes for a notification center and an organization-scoped activity timeline.

## Backend audit

The `notifications` model exists, but `routes/api.php` exposes no notification list, unread-count, or mark-read endpoint. The old frontend notification service referenced `/notifications`, `/notifications/{id}/read`, and `/notifications/send`; none exist in Laravel. Those unsupported calls were removed. The notification page and header therefore show an explicit unavailable state, make no notification API request, and render no badge or sample data.

Laravel exposes organization-scoped automation executions at `GET /automation/logs`. `AutomationLogController` checks the authenticated user's organization, `automation.view`, and the `automation.basic` entitlement. This is the sole activity source. Device, billing, and organization activity are not combined because no corresponding history endpoints exist.

## Routes

- `/app/notifications` — authenticated notification center; unavailable until Laravel exposes inbox APIs
- `/app/activity` — automation-gated, server-recorded execution activity

Client-side activity filters operate only on records returned by Laravel. Resource links are created only when the automation and execution identifiers are present in the API response.
