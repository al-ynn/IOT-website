# Admin platform migration

Phase 25 replaces the legacy admin cards with a compact administration console using the shared design system and admin shell.

## Security

All `/admin/*` routes remain wrapped by `ProtectedRoute platformAdmin`, which checks `user.platformRole === "platform_admin"` and does not inspect organization roles. Every corresponding API route remains inside Laravel's `admin` middleware, whose `EnsurePlatformAdmin` calls the canonical `isPlatformAdmin()` model method. Customer organization administrators therefore cannot use either the frontend routes or backend endpoints.

## Supported APIs

- `GET /admin/overview`
- `GET /admin/users`
- `PATCH /admin/users/{id}/status`
- `GET /admin/organizations`
- `PATCH /admin/organizations/{id}/status`
- `GET /admin/billing/organizations`
- `GET /admin/billing/stats`
- `GET /billing/plans`

Search and status filters are applied only to complete arrays returned by Laravel because server pagination/filter parameters are not implemented. User and organization activation/suspension use the supported endpoints. The UI does not provide billing mutations.

Laravel exposes no platform health endpoint, admin payment event feed, organization detail endpoint, or admin pagination. These areas show honest unavailable notes and never reuse customer-scoped data or invent status.
