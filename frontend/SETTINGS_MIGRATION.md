# Settings migration

Phase 23 replaces the legacy combined profile page and the deferred workspace-settings route with a responsive settings area.

## Routes

- `/app/settings` — overview, billing shortcut, and logout
- `/app/profile` — name and email
- `/app/security` — password change
- `/app/preferences` — persisted light/dark theme
- `/app/notifications/settings` — backend notification preferences

## Contract notes

Notification fields now match Laravel: `emailAlerts`, `deviceAlerts`, `weeklyReports`, and `billingAlerts`. A password change revokes all Sanctum tokens, so the UI sends the user back to login after success. Two-factor authentication, session management, avatar upload, account deactivation/deletion, personal timezone, and personal language are presented only as unavailable because no supporting API exists.

The organization settings route remains `/app/settings/organization` and is not merged into personal settings.
