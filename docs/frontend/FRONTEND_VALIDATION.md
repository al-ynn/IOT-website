# Frontend Validation

## Parameter / Datastream completion — 2026-09-03

- `npx.cmd tsc --noEmit -p tsconfig.app.json`: pass.
- `npm.cmd run lint`: 0 errors, 0 warnings.
- `npm.cmd run build`: pass; entry 405.78 kB raw / 124.94 kB gzip; largest lazy chunk 98.97 kB raw / 27.60 kB gzip.
- `npx.cmd playwright test e2e/template-workspace.spec.ts`: 1 passed, 0 failed, 0 skipped.
- The existing browser flow covers authorized Template onboarding. Dedicated browser cases for every type, runtime values, and widget type filtering are not yet configured; backend contract tests provide deterministic typed-ingestion coverage.

This file validates the frontend that defines the backend-consumption requirements. A green frontend build proves that the contract source is internally healthy; it does not prove that a backend implementation satisfies every service, DTO, error, authorization, or workflow contract.

## Current Status

**GREEN WITH EXTERNAL PRE-PRODUCTION GAPS.** The current repository-level frontend gates and the release-representative Chrome suite pass. Production infrastructure, additional browser engines, real assistive-technology validation, and formal certification remain external work.

## Last Validated

- Date: 2026-09-01
- Commit before documentation consolidation: `886f279`
- Branch: `main`
- Working tree: contains extensive pre-existing roadmap changes; this cleanup changes documentation only

## Commands

Run hard gates from `frontend/`:

```powershell
npx.cmd tsc --noEmit -p tsconfig.app.json
npm.cmd run lint
npm.cmd run build
npm.cmd run test:e2e

On Windows, browser acceptance uses the repository-owned server harness (`frontend/e2e/run-browser-tests.mjs`) rather than Playwright `webServer`. The minimal managed-server reproducer prevented reporter finalization in this environment. The harness prepares the browser SQLite database, starts exact Laravel/Vite children, runs Playwright with `playwright.external.config.ts`, awaits completion, and verifies owned ports are released.
```

There is no standalone component-test runner. Playwright provides the configured frontend behavior, accessibility, responsive, and adversarial integration suite.

## TypeScript

Latest result: **PASS** using `npx.cmd tsc --noEmit -p tsconfig.app.json`.

## Lint

Latest result: **PASS** using `npm.cmd run lint`.

- Errors: 0
- Warnings: 0

## Production Build

Latest result: **PASS** using `npm.cmd run build`.

- Entry: 405.54 kB raw / 124.89 kB gzip
- Largest lazy chunk: DashboardWorkspace, 91.99 kB raw / 26.09 kB gzip
- Source maps: disabled; zero `.map` files
- No application development endpoint or secret fixture was embedded

These are build observations, not production latency claims.

## Frontend Tests

- Standalone unit/component runner: not configured
- Playwright integration tests: 32
- Passed: 32
- Failed: 0
- Skipped: 0
- Retried: 0
- Duration: 7.0 minutes

TypeScript, lint, and build are not substitutes for browser behavior tests; the configured Playwright suite supplies that layer.

## Browser QA

- Framework/browser: Playwright 1.62 / Chrome
- Viewports: 320×568, 375×667, 768×1024, 1024×768, 1440×900
- Result: **PASS**, 32/32
- Covered: login/logout, identity transition, personal/Admin scope, assigned/unassigned/revoked Device access, Device sharing, Dashboard source authorization, direct URLs, history, responsive navigation, stale Save, Draft, Pull, Review, publication, Notifications, Comments/XSS, and lifecycle
- Firefox, WebKit/Safari, and physical-device browser validation: not executed

## Accessibility

- Automated tooling: `@axe-core/playwright`
- Ten axe page checks: PASS with no critical/serious finding
- Keyboard/focus: navigation, resource tabs, mobile drawer focus/Escape/restore, Search, filters, Comments, Pull, and Notification actions passed
- Reflow: PASS at 200% and 400% equivalents
- Real screen reader: not executed
- Formal accessibility certification: not performed

## Security-Sensitive Frontend Validation

- Logout/account-switch and protected direct-route behavior: PASS
- Assigned, unassigned, and revoked Device boundaries: PASS
- Dashboard source authorization: PASS
- Exact submitted/published Revision flow: PASS
- Comment HTML-like fixture remained inert: PASS
- Fake-secret DOM/network regression: PASS
- Source maps: disabled
- No server secret in `VITE_*`: PASS
- External penetration test: not performed

The bearer token is currently stored as `iot_token` in localStorage. Treat this as a documented security posture requiring strong XSS prevention; do not store any additional secret or private server state there.

## Performance Validation

The Vite production build passes. Deterministic Phase102 evidence covers server pagination, bounded previews, request/query batching, Activity authorization before effective pagination, Overview fan-out, and Dashboard source batching. No production p50/p95/p99 claim is made.

## Route QA

One browser router, protected `/app/*` and `/admin/*` fallbacks, lazy authenticated pages, and `/admin/dashboard` redirection are present. Direct URL, refresh, back/forward, lazy routes, Admin/Staff transitions, and identity changes passed in Chrome.

## Responsive QA

All five required viewports passed the core workspace flow with no release-blocking navigation, overflow, or interaction finding.

## Known Current Gaps

| Classification | Surface | Current gap | Release effect |
|---|---|---|---|
| P2 | Authentication storage | Bearer token persists in localStorage | Non-blocking repository RC; retain strong XSS controls and review deployment posture |
| P2 | Server state | React Query adoption is partial | Non-blocking; do not create a competing state architecture |
| External | Browsers/AT | Firefox, WebKit/Safari, physical-device browser, and real screen reader not executed | Validate according to production support policy |
| External | Deployment | Production proxy, headers, multi-node cache/session, and real infrastructure not executed | Required before applicable production clearance |

## Release Status

**RELEASE CANDIDATE READY WITH EXTERNAL PRE-PRODUCTION GATES.** Repository-level frontend hard gates pass with no unresolved P0/P1. This is not a claim that unexecuted production or certification environments passed.

## Update Rule

Update this file whenever TypeScript, lint, build, frontend tests, browser behavior, accessibility, security-sensitive rendering, route QA, responsive QA, or performance evidence changes. Do not create phase-, audit-, matrix-, or feature-specific frontend validation documents.
