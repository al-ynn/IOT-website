# Production hardening audit

Date: 2026-08-12

## Executive summary

The application has sound core authorization boundaries: Sanctum protects private APIs, platform administration uses only the canonical `platform_role`, and customer resource controllers scope devices, dashboards, analytics, automation, invitations, billing records, and activity to the authenticated organization. Phase 27 adds auth throttling, telemetry permission enforcement, stale-token cleanup, route-level code splitting, and database integrity/query indexes.

The codebase builds and its 42 backend tests pass. It is not yet ready for an unattended production deployment until production environment values, HTTPS, trusted origins, queue/process supervision, backups, monitoring, and a real browser accessibility/responsive pass are completed.

## Performance

- Dashboard, device, telemetry, analytics, automation, billing, and admin routes are loaded with `React.lazy` under a shared `Suspense` boundary.
- The previous single JavaScript asset was 1,906.95 kB (597.01 kB gzip).
- After route splitting, the entry asset is 521.93 kB (154.70 kB gzip), a 72.6% raw-size and 74.1% gzip reduction. Feature chunks are emitted independently.
- The shared chart chunk is still 1,138.04 kB (378.12 kB gzip). It is loaded only by routes that render charts, but replacing overlapping chart libraries or splitting individual chart implementations is the next performance target.
- Notification inbox and automation execution timeline composite indexes were added for their expected access patterns.

## Frontend security

- Bearer tokens are never placed in URLs or API responses beyond the login response.
- Axios now removes an invalid local bearer token after any 401 response.
- Protected routes enforce authentication, platform administration, organization presence, permissions, roles, and billing gates as appropriate.
- Frontend checks remain convenience boundaries only; Laravel independently authorizes protected operations.
- The existing bearer token is stored in `localStorage`. This preserves the established authentication architecture but means an XSS vulnerability could expose it. A future migration to first-party, HTTP-only Sanctum cookies would reduce this risk and requires coordinated backend/CORS/CSRF work.
- `npm audit --omit=dev` reported zero production dependency vulnerabilities.

## Backend security

- Login is limited to five requests per minute per Laravel throttle key; registration is limited to three.
- Telemetry ingestion now requires `device.manage` and still verifies that the device belongs to the user's organization.
- Telemetry identifiers, keys, and units now have bounded lengths.
- Platform admin middleware calls `User::isPlatformAdmin()` and does not reuse customer roles.
- Resource lookups in device, dashboard, analytics, automation, invitation, billing, and activity controllers are organization-scoped.
- Controller mutations use validated allowlists rather than raw request mass assignment. Automation uses its dedicated validator/service contract.
- Production exception detail is controlled by Laravel's `APP_DEBUG`; API-specific billing exceptions return stable codes without stack traces.
- `composer audit --no-dev --locked` reported no security vulnerability advisories.

## Database

- The deployed PostgreSQL database already contained the user/organization foreign key. The new migration detects this portably before adding one on databases where it is absent.
- Added `notifications (organization_id, user_id, read_at, created_at)` index.
- Added `automation_execution_logs (automation_execution_id, executed_at)` index.
- Existing telemetry and automation execution range queries already have composite indexes.
- Cascades are present for organization-owned domain records; the user organization relation is nullable on organization deletion.

## Environment and operations

- `.env`, `.env.production`, database files, logs, vendor, build output, and secrets are ignored by the backend repository rules.
- The checked local runtime correctly has `APP_ENV=local`, `APP_DEBUG=true`, local URLs, and debug logging. These values must not be copied to production.
- Production must set `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, a strong unique `APP_KEY`, production database credentials, restrictive Sanctum/CORS trusted origins, and an HTTPS frontend API URL.
- Development seed credentials are guarded to local/testing environments and must not be invoked through a production-forced environment.
- Configure queue workers, scheduler, log aggregation, error monitoring, database backups/restore drills, health monitoring, and rate-limit/cache stores before launch.

## Quality, accessibility, and responsive review

- TypeScript strict project checks pass and route imports resolve.
- Modified Phase 27 frontend files pass ESLint.
- Full frontend lint improved substantially after moving lazy component declarations out of router configuration, but 18 existing React effect-state errors and six dependency warnings remain across legacy contexts and data-loading pages. They should be refactored rather than suppressed.
- Shared controls provide labels, focus rings, semantic tables, dialog roles, loading announcements, and responsive overflow/card patterns.
- Code inspection supports 320 px through desktop layouts, but automated or manual browser testing at 320, 768, 1024, and 1440 px was not available in this run.

## Verification

- `npx.cmd tsc --noEmit -p tsconfig.app.json`: passed
- `npm.cmd run build`: passed
- `php artisan optimize:clear`: passed
- `php artisan route:list`: passed, 85 routes
- `php artisan migrate --force`: passed
- `php artisan test`: 42 passed, 276 assertions
- Duplicate frontend route paths: none found during audit

## Remaining launch blockers and risks

1. Replace or formally accept the localStorage bearer-token threat model.
2. Finish the remaining React effect lint refactors.
3. Reduce the 1.14 MB lazy chart chunk by consolidating the overlapping chart libraries.
4. Perform browser-based keyboard, screen-reader, responsive, and critical-flow testing.
5. Validate production environment, HTTPS, CORS/Sanctum origins, queues, scheduler, monitoring, and backups in staging.
6. Run database migration and rollback rehearsals against a production-like snapshot.
