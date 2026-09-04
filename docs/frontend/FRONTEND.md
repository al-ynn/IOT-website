# Frontend

## Purpose

This is the authoritative description of the current React, Vite, and TypeScript frontend for the company-operated IoT platform. The application serves Admin and Staff users, but the backend remains authoritative for authentication, tenant scope, authorization, lifecycle, revisions, review, publication, and every protected effect.

This frontend is the primary product-contract input for backend development. Backend requirements come from routed pages, service functions, API-facing types, workflow states, and browser behavior—not from generic API assumptions or unverified existing backend behavior.

## Stack

| Concern | Current implementation |
|---|---|
| UI | React 19.2 |
| Language | TypeScript 6.0, project references |
| Build | Vite 8.2 |
| Routing | React Router DOM 7.18, one `createBrowserRouter` configuration |
| Server data | Axios 1.19 and TanStack React Query 5.101 |
| Forms | React Hook Form 7.84, Zod 4.4, and resolver integration where adopted |
| Styling | Tailwind CSS 4.3 plus design-system tokens |
| Local stores | React context, component state, and Zustand where a feature uses it |
| Head metadata | React Helmet Async |
| Motion and complex UI | Framer Motion, React Grid Layout, React Flow, Lucide icons |
| Frontend tests | TypeScript, ESLint, and Vite gates; no standalone component-test runner |
| Browser tests | Playwright 1.62 release suite under `frontend/e2e/` |
| Accessibility automation | `@axe-core/playwright` plus keyboard, focus, and reflow checks |

Dependencies and scripts are defined in `frontend/package.json`.

## Application shell

All authenticated `/app/*` and `/admin/*` pages render inside `src/layouts/AppLayout.tsx`. It composes `AppHeader`, one primary sidebar, an optional contextual secondary sidebar, and equivalent mobile drawers. Desktop collapse preferences and the theme preference are stored locally. The shell has one `<main id="main-content">` landmark.

There is one AppLayout. Admin users use the same shell with an Administration section. Resource tabs are page content, not a third sidebar.

## Routes

`src/app/router/router.tsx` is the route source of truth. All authenticated pages below are lazy-loaded through `lazy-pages.tsx`; login, invitation, denial, and not-found pages are eager.

| Route | Page/context | Access presentation | Purpose |
|---|---|---|---|
| `/login`, `/invite/:token`, `/unauthorized` | Auth | Public or session transition | Sign-in, invitation acceptance, safe denial |
| `/app/dashboard` | Dashboard | Personal | Current user's dashboard and monitored scope |
| `/app/collaboration` | Collaboration Overview | Personal | Bounded collaboration summaries and search affordance |
| `/app/my-work` | My Work | Personal | Current authorized work relationships |
| `/app/shared-with-me` | Shared With Me | Personal | Current direct assignments and grants |
| `/app/changes` | Changes Inbox | Personal | Pull-managed resources behind accepted presentation |
| `/app/activity` | Activity | Personal | Current-authorized collaboration activity |
| `/app/search` | Search | Personal | Authorized cross-domain search |
| `/app/notifications` | Notifications | Personal | Current user's notification center |
| `/app/dashboard/:id[/*]` | Dashboard detail/share/publication | Personal/private | Dashboard collaboration and publication workflows |
| `/app/dashboard/published[/:id]` | Published dashboards | Published | Pinned published presentations |
| `/app/devices[/:id]` | Devices | Personal | Assigned Device collections and details |
| `/app/devices/:id/share`, `/app/shares/:id` | Device sharing | Personal workflow | Request, recipient, and status UI |
| `/app/analytics[/*]` | Analytics | Personal | Authorized telemetry analysis |
| `/app/reports[/:id]` | Reports | Personal | Report definitions, runs, and downloads |
| `/app/automations[/*]` | Automations | Personal | Automation definitions and editing |
| `/app/fleet` | Fleet | Personal | Authorized Device fleet view |
| `/app/locations[/:id]` | Locations | Personal | Location collections and resource detail |
| `/app/developer/templates[/:id]` | Templates | Personal | Device template collaboration |
| `/app/developer/firmware` | Firmware | Personal | Firmware and release surfaces |
| `/app/developer/credentials` | Device credentials | Personal | Authorized credential management |
| `/app/developer/integrations` | Integrations | Personal | Integration surface |
| `/app/developer/webhooks[/:id]` | Webhooks | Personal | Webhook definitions and details |
| `/app/debug/{provisioning,events,crashes}[/*]` | Operational diagnostics | Personal/permission-gated | Provisioning, operational events, and crash reports |
| `/admin/overview` | Global Operations | Admin | Administrative operational overview |
| `/admin/devices[/:id]`, `/admin/locations[/:id]` | Global resource views | Admin | Administrative Device and Location views |
| `/admin/device-access`, `/admin/access-requests` | Access governance | Admin | Assignments and access requests |
| `/admin/reviews[/*]`, `/admin/*-submissions/:id` | Review Center | Admin | Review queues, ownership, and decisions |
| `/admin/resources[/:type/:id]` | Resource inventory | Admin | Canonical inventory and resource pointers |
| `/admin/recently-created`, `/admin/recently-updated` | Recent resources | Admin | Creation- and revision-based inventory views |
| `/admin/disabled-resources`, `/admin/needs-attention` | Governance views | Admin | Lifecycle and attention projections |
| `/admin/users[/:id]`, `/admin/organizations`, `/admin/system` | Administration | Admin | Users, organizations, and system settings |

Unknown `/app/*` and `/admin/*` paths render the protected not-found page. `/admin/dashboard` redirects to `/admin/overview`.

## Personal and administrative context

The product contract is `/app/*` for the current user's personal application context and `/admin/*` for explicit Admin-global, current-Organization workflows. The UI must never elevate scope through client parameters. Current code identifies administrators with `platformRole === "platform_admin"`; repository authorization evidence reports that the implemented platform-wide scope conflicts with the intended current-Organization contract. This is a blocking known limitation and must not be normalized in frontend code or hidden in documentation.

## Major workspaces

- Dashboard presents personal Device/dashboard data and links to private and published dashboard detail.
- Collaboration Overview composes My Work, Shared With Me, Changes, Notifications, Activity, and Search entry points without becoming another dashboard engine.
- My Work reports factual creator, contributor, and draft relationships only while current authorization exists.
- Shared With Me reports current direct Device assignments and canonical non-Device grants.
- Changes contains only currently authorized, behind, pull-managed presentations.
- Search is server-ranked, paginated, presentation-aware, and authorization-filtered by the API.
- Notifications are personal; unread, read, dismiss, and actionability are distinct states.
- Activity displays safe, current-authorized collaboration events rather than runtime telemetry.
- Review Center is an explicit Admin workflow with ownership and exact-submitted-revision semantics.
- Administrative resource views are explicit inventory, recent, lifecycle, access, and review workspaces.

## Resource detail and tabs

`src/navigation/resource-tabs.ts` is the current tab registry for Device and Location details. Device tabs may include Overview, Dashboard, Parameters, Comments, Revisions, Telemetry, Analytics, Configuration, Snapshots, Credentials, and Crash Reports according to context and capability DTOs. The Parameters tab presents canonical definitions with their latest authorized values and explicit no-data state. Location exposes Overview, Comments, and Revisions. The `tab` query parameter supports direct links; unsupported or unavailable tabs resolve to the visible default. Other resource pages currently use their own page structure rather than this registry.

## Shared components

Shared primitives are exported from `src/components/ui`: Button, Card, form controls, DataTable, status/feedback states, Toast, Modal/Drawer, Icon, and typography. Major domain components include the application sidebars/header, `ResourceTabs`, collaboration comments and revisions, lifecycle/attention controls, dashboard workspace/widgets, notification components, Device workspace components, and Admin access/user components. Extend these primitives when semantics are genuinely shared; do not establish competing dialog, table, toast, badge, or navigation systems.

## API / Data Fetching

The backend implementation contract derived from the frontend services, types, pages, and workflow states is maintained in `../backend/BACKEND.md`. Backend work must start from that contract instead of treating visible routes as permission rules or implementing generic CRUD independently of frontend behavior.

Use this order when defining backend work: routed workflow; service method/path/request/response; API-facing TypeScript DTO; page loading/empty/denied/validation/conflict/failure states; then server-owned authorization, tenant, transaction, secret, and runtime rules. Unused frontend service functions do not automatically require endpoints, and existing backend endpoints do not automatically become product requirements.

`src/services/api.ts` owns the Axios client and `/api` default base URL. Feature services define typed requests. The client currently attaches the bearer token stored as `iot_token` and clears it after a 401. TanStack React Query is provided application-wide with one retry and window-focus refetch disabled, although several pages still use local effects and service calls directly.

Lists must use API pagination and bounded payloads. Mutations show pending/error state and invalidate or reload the affected server state. Rapid searches and other replaceable requests must cancel or ignore stale responses. The frontend must not fetch all protected rows and filter authorization locally.

## Server state and local state

Server state includes resources, authorization-derived capabilities, access labels, lifecycle, revisions, drafts, accepted presentation, search, activity, notifications, review, publication, and collaboration projections. It must be refreshed after relevant mutations and never treated as authoritative merely because it is cached.

Local state includes open overlays, temporary form input, selected/URL tab, filter controls, mobile drawer state, sidebar collapse preference, and theme. Local storage currently contains the bearer token plus non-sensitive theme/sidebar preferences; it must not contain resource configurations, authorization snapshots, drafts, review data, or secrets.

## Authorization boundary

**The frontend is not the authorization engine.** Roles, capability DTOs, badges, and visibility flags are presentation aids. Every protected API request reauthorizes on the backend. React must not reproduce backend policy logic.

Creator, contributor, comment, mention, notification, share request, review ownership, publication, and reference relationships are not authorization. Staff Device access remains assignment-driven on the backend. Dashboard access does not grant Device-source access, Device access does not grant private Location access, and Location access does not grant Device access.

## Access and collaboration vocabulary

Device UI uses **Viewer** and **Full Access**. Full Access is not Admin, and same Organization is not sufficient Device access. Canonical non-Device collaboration uses **View** and **Edit** where implemented. These labels are distinct and remain textual.

## Revisions, drafts, conflicts, and Pull

Revision history and comparison show safe revision metadata and requested snapshots. A stale save is a 409 conflict: there is no Force Save, silent overwrite, automatic merge, or branch. A Draft is private working state, not a branch or authorization source. Apply Draft returns through canonical Safe Save.

Pull advances only the current user's accepted presentation for a pull-managed resource. Pull is not Save and does not mutate canonical configuration. Changes may offer Pull, Ignore, and Reminder according to server capabilities. Location has no Pull.

## Review, publication, and lifecycle

Review states presented by the UI include Available, Mine, In Review by Other, Reviewer Unavailable, and Completed. Opening a review does not claim it. Start Review, Release, and confirmed Take Over are explicit. Approve, Request Changes, and Reject operate on server-issued capabilities and the exact submitted revision. Publication presents the approved revision rather than a newer private revision.

Lifecycle state is textual. Active and Disabled are distinct; Disabled does not mean Offline. Restore does not imply regrant, republish, reactivation, Pull, or Draft application.

## Device Template workspace

The Template list opens a bounded creation dialog for name, allowlisted hardware, allowlisted connection type, and description, then navigates directly to a refresh-safe workspace. Its Home checklist is derived from current Template metadata, canonical Template parameters (shown as Datastreams), Template Web Dashboard configuration, and associated Devices. Settings use the existing Template Safe Save endpoint; firmware configuration displays only the non-secret Template ID and name. Datastreams remain `DeviceTemplateParameter` records. Their editor offers safe presets plus type-aware configuration for Number, Integer, String, Boolean, and Enum. Location is not presented as a parameter type because no coordinate telemetry contract exists. The Web Dashboard reuses the shared editor and binds widgets through authorized Device/parameter selectors with numeric compatibility filtering. Template-bound Device creation derives the Template and current Organization from the route and authenticated context.

## Dashboards

The frontend has one dashboard engine for Device and independent/shared dashboards. The widget renderer registry maps trusted widget types to components; React Grid Layout supports editor layout. Source responses remain backend-authorized per current viewer. A shared Dashboard can display an unavailable/restricted source and does not grant Device access. Published dashboard presentation remains separate from private editing and comments.

The editor palette exposes 19 core widget contracts: Switch, Slider, Label, Device count, Device table, Geomap, Image map, Device connection map, Metrics over time, Metric by devices, Event count tile/chart, Latest events, Events over time/breakdown/by organization/by Device/by template, and Activations. Palette items support click, keyboard, and drag-to-canvas creation; canvas widgets support grid move/resize, keyboard layout controls, configuration, removal, Safe Save, and reload. Controls are explicitly read-only until a supported Device write transport exists. Geomap uses the authorization-qualified projection endpoint; Image Map retrieves protected dashboard assets through the authenticated API and stores normalized marker positions, never arbitrary public URLs.

## Responsive architecture

Tailwind's responsive utilities currently drive layout, chiefly `sm` and `lg` changes. The shell switches from mobile drawers to fixed primary/secondary sidebars at `lg`; content grids and forms generally expand at `sm` or `lg`. Wide data tables use bounded horizontal containers. Dialogs use viewport padding, a full-width mobile base, constrained maximum width, and bounded vertical scrolling. Required validation viewports are 320×568, 375×667, 768×1024, 1024×768, and 1440×900.

## Loading, empty, and error states

Use the shared LoadingState, Skeleton, EmptyState, and ErrorState patterns. Loading and errors must be textual; retries must be explicit and safe. Empty wording describes the real workspace accurately. Do not expose stack traces, SQL, model names, or sensitive backend detail.

## Security-sensitive rules

Never place secrets in the DOM, accessible hidden text, logs, query strings, browser storage, or `VITE_*` variables. Do not render uncontrolled HTML, accept arbitrary model/class identifiers, infer authorization from client state, or permit client-selected user/Organization scope elevation. URLs derived from server data must resolve through known internal routes.

## Accessibility summary

Use native HTML first, visible focus, keyboard-operable controls, labeled forms, textual state, and non-color-only meaning. Links navigate and buttons act. Dialogs and drawers require correct accessible names, focus containment, escape handling, and focus restoration. Current overlays have dialog names and Escape handling but lack complete focus containment/restoration; see validation gaps.

## Performance summary

Major authenticated pages are route-lazy-loaded. Keep pagination and previews server-bounded, avoid request-per-row and uncontrolled fan-out, reuse canonical query state, and prevent stale search responses. Never improve apparent speed by downloading private datasets or moving authorization filtering to the browser.

## Source map

| Path | Responsibility |
|---|---|
| `frontend/src/app` | Providers, router, metadata, application startup |
| `frontend/src/layouts` | Authenticated and public shells |
| `frontend/src/navigation` | Primary, contextual secondary, public, and resource-tab registries |
| `frontend/src/pages/app` | Personal application pages |
| `frontend/src/pages/admin` | Explicit administrative pages |
| `frontend/src/pages/auth`, `pages/public` | Authentication and public content |
| `frontend/src/components/ui` | Shared UI primitives |
| `frontend/src/components/{collaboration,dashboard,device,layout,notifications}` | Major reusable feature UI |
| `frontend/src/services` | API client and typed endpoint adapters |
| `frontend/src/context`, `hooks` | Identity, Organization, permissions, and reusable UI behavior |
| `frontend/src/types` | API and presentation types |
| `frontend/src/design-system` | Tokens, themes, and styling foundations |

## Current known limitations

| Severity | Issue | Impact | Target |
|---|---|---|---|
| Resolved | Admin is constrained to the authenticated Admin's current Organization | Cross-tenant Admin parameters, resources, projections, and authorizer calls are denied | Phase101 regression suite |
| P1 | Editing-presence frontend adoption is incomplete | Some editors do not expose the canonical advisory lease workflow | PRE-102 remediation |
| Closed | Safe Save and idempotency writer adoption | Canonical user-initiated revision writers send a stable UUID for each logical payload and preserve it across ambiguous retries | R05 complete |
| Closed | Notification materialization adoption | Collaboration facts use the recoverable outbox and canonical deduplicating materializer | R05 complete |
| P1 | No real-browser, keyboard, or automated accessibility runner is configured | Responsive workflows and accessibility cannot be release-qualified | Phase103/104 tooling and execution |
| P2 | Modal/Drawer lacks complete focus containment and restoration | Keyboard dialog behavior is not yet proven accessible | Accessibility remediation |
| P2 | Authentication bearer token is persisted in localStorage | XSS would increase token exposure impact | Security architecture review |
| P2 | React Query adoption is partial | Cache invalidation and stale-request behavior vary by page | Incremental frontend standardization |

## Updating this document

Any frontend change affecting architecture, routes, workspaces, major components, state, collaboration semantics, or the frontend/backend boundary must update this file in the same change. Do not create feature-specific frontend architecture documents.
