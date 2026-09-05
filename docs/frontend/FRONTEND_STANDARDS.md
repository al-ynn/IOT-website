# Frontend Standards

## Purpose

These are the canonical engineering rules for every frontend change. Use the existing React/Vite/TypeScript architecture, keep the backend authoritative, and update shared patterns rather than creating a second implementation.

## General implementation

- Use strict, explicit TypeScript types at API and component boundaries.
- Reuse current layouts, registries, services, hooks, and design-system tokens.
- Keep feature state close to the feature unless it is genuinely shared.
- Do not create parallel application shells, routers, dashboard engines, or collaboration workspaces.
- Preserve current product vocabulary and company-operated platform semantics.

## Components

Reuse shared Button, form controls, Card, DataTable, Modal/Drawer, Toast, Badge/status, feedback, icon, and typography primitives. Reuse canonical navigation, ResourceTabs, resource identity, access/lifecycle labels, and collaboration controls. A new primitive needs a proven semantic difference, not a styling preference.

Links navigate. Buttons mutate, submit, or toggle. Do not use clickable `div`/`span` elements when native controls work. Avoid nested interactive controls and duplicate desktop/mobile DOM that remains accessible simultaneously.

## Routing

- Keep one `createBrowserRouter` architecture and lazy-load major pages where appropriate.
- Direct URLs, refresh, back, and forward must work without in-memory setup.
- Unknown protected paths use the safe not-found route.
- Do not create alternate pages for a canonical workspace.
- Resource tabs follow their registry and URL contract; routed links are not ARIA tabs.

## Personal and Admin rules

`/app/*` is personal/current-user context. `/admin/*` is explicit Admin-global current-Organization context. Never add `global=true`, `admin=true`, another user's ID, or another Organization ID to elevate scope. Backend authorization must enforce this contract; navigation visibility is not enforcement.

## Authorization

Frontend roles, capabilities, access badges, and visibility flags are UX only. Every protected request must be authorized by the backend. Never reconstruct backend policy or infer access from creator, contributor, comment, mention, notification, share request, review ownership, publication, or reference relationships.

Device UI uses Viewer and Full Access. Full Access is not Admin; same Organization is not Device authority. Non-Device collaboration uses View and Edit where supported. Dashboard sharing does not grant Device-source access.

## Forms

- Provide a programmatic visible label; a placeholder is not a label.
- Associate helper and error text and preserve safe user input after validation failure.
- Show pending state and prevent accidental double submission.
- Keep server validation authoritative and render safe 422 feedback.
- Require explicit confirmation for destructive actions; never silently discard work.
- Group related controls with native fieldset/legend where appropriate.

## Data fetching

Use the canonical Axios client and typed feature services. Use TanStack React Query for cacheable server data when adopting shared query state; otherwise retain explicit cleanup in effects. Paginate on the server, bound page sizes/previews, cancel or ignore stale requests, and avoid request-per-row behavior. Never fetch all protected data and filter it in React.

Frontend services and API-facing types are the backend-consumption contract. Keep method, path, parameters, body, envelope, DTO, pagination, and error expectations explicit. Do not add speculative service functions for hypothetical backend features.

## Query cache and identity changes

Cache is not authority. Keys must isolate every relevant current-user, Organization, resource, and presentation context. Clear or invalidate private server state on logout and identity/context changes. Never persist secrets, drafts, full private configuration, review detail, or authorization snapshots in cache or browser storage.

## Mutations and errors

Mutations call the canonical backend endpoint, expose a pending state, prevent duplicate action, and refresh only affected server state. Honor server idempotency contracts where required. Handle 401/403, 404, 409, 422, and unexpected failures distinctly and safely. Never show raw stack traces, SQL, filesystem paths, or model serialization.

## Stale Save, Draft, and Pull

- A 409 is a stale conflict. No Force Save, silent overwrite, automatic merge, or branch.
- Draft is private working state, not authorization or a branch; Apply Draft uses Safe Save.
- Active collaborative editors use advisory editing presence. Presence never authorizes, locks, or disables Save; current authorization and Safe Save conflict checks remain authoritative.
- A logical save payload receives one UUID `Idempotency-Key`. Ambiguous retries reuse it; a changed command receives a new UUID; the key is never placed in URLs or persisted as user content.
- Pull appears only when the backend capability/presentation policy allows it.
- Pull advances personal accepted presentation; it is not canonical Save.
- Location must never expose Pull.
- Ignore and Reminder do not imply acceptance.

## Review and publication

Opening a review never claims it. Start Review, Release, and confirmed Take Over are explicit. Decision buttons follow current server capability and exact submitted-revision state. Publication shows the approved revision and must not reveal private latest revisions, drafts, comments, or review internals.

## Accessibility

- Use native semantic HTML first; add ARIA only when accurate.
- Provide a meaningful page heading and main/navigation landmarks.
- Make every action keyboard-operable with a visible focus indicator.
- Dialogs/drawers need an accessible title, sensible initial focus, focus containment, Escape behavior where safe, background isolation, and trigger-focus restoration.
- Icon-only controls need contextual accessible names.
- Labels, errors, loading, success, access, lifecycle, review, unread, and update state must be textual and not color-only.
- Hide decorative icons/skeletons from the accessibility tree.
- Do not leave closed drawers, dialogs, or responsive duplicates tabbable.
- Announce meaningful asynchronous state concisely; never place whole lists in live regions.
- Do not add accessibility overlays, assistive-technology detection, or disability tracking.

## Responsive interaction

Core workflows must remain usable at 320×568, 375×667, 768×1024, 1024×768, and 1440×900. Avoid page-level horizontal overflow, keep navigation reachable, fit dialogs within the viewport, preserve labels when tables become cards, and maintain logical DOM/reading order. Required drag or resize interactions need keyboard alternatives.

## Security

- Never use `dangerouslySetInnerHTML` with untrusted data or generate `javascript:` links.
- Never place secrets in localStorage, sessionStorage, IndexedDB, the DOM, hidden accessible text, data attributes, logs, URLs, or public environment variables.
- Every `VITE_*` value is public; server secrets do not belong there.
- Never authorize from frontend state or expose protected controls as functional without backend approval.
- Resolve destinations through known internal routes; do not accept arbitrary external redirects.
- Do not expose arbitrary FQCN/model/table names or allow client tenant/user scope elevation.
- Do not add employee surveillance, presence history, productivity scoring, or commercial feature gates.

## Performance

Lazy-load major routes, paginate server-side, bound preview payloads, deduplicate identical requests, and avoid large global stores. Polling and editing heartbeats need one intentional loop with bounded intervals. Do not load giant datasets for local search/filtering or optimize by weakening authorization.

## Vocabulary

Preserve these exact meanings: Admin, Staff, Viewer, Full Access, View, Edit, Active, Disabled, Draft, Published, Available, Mine, In Review by Other, Reviewer Unavailable, and Completed. Disabled is not Offline. Do not introduce Owner as an authorization concept or any commercial access tier.

## Testability

Prefer role, label, and other semantic selectors. Add `data-testid` only when a stable semantic locator is impossible. Do not add production test-login, impersonation, reset, profiler, or authorization-bypass routes. Avoid arbitrary sleeps; wait for observable state.

## Documentation rule

Every meaningful frontend architecture or pattern change must update `FRONTEND.md` and/or this file in the same work. Validation changes update `FRONTEND_VALIDATION.md`. New feature-specific frontend Markdown documents are prohibited by default.

Any HTTP call, API-facing DTO, capability, pagination, or handled-error change must also update the canonical backend guide when it changes what the backend must provide.
