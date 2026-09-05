# Backend

## Status and purpose

This is the canonical guide for developing the backend required by the current frontend. It describes the backend we need to build from verified frontend contracts; it is not a description of whatever backend code happens to exist today.

The requirement direction is: frontend route/page/workflow → frontend service function → request and response TypeScript contract → required backend endpoint, DTO, policy, transaction, and tests.

Current frontend code is authoritative for what the client sends, consumes, and displays. The backend being developed remains authoritative for authentication, Organization scope, authorization, validation, transactions, secrets, and protected effects. A visible frontend button or capability does not itself grant authority.

The product is a company-operated IoT platform. Product roles are Admin and Staff. Staff Device access is Viewer or Full Access through canonical Device assignments only. Supported non-Device private collaboration uses View/Edit.

## Contract sources and inventory

| Source | Fresh count | Role |
|---|---:|---|
| `frontend/src/services/*.ts` | 56 files, 263 exported functions | HTTP method/path/body/envelope source |
| API-facing frontend types | 62 files | Consumed DTO vocabulary |
| Pages | 78 | Workflow and state consumers |
| Hooks | 5 | Shared lifecycle behavior |
| Router | 2 files | User-visible capability coverage |
| Playwright E2E | 3 files | Accepted browser workflows |
| Existing Laravel routes | 364 | Gap/reference input only; not the backend requirements source |
| Canonical frontend docs | 3 | Current architecture, standards, validation |

Of 263 exported service functions, 209 are referenced by current UI/engine code and 54 are presently unused. The extractor found 254 direct Axios calls: 77 statically literal calls matched an exact Laravel method/path, 167 use interpolation or helper-composed paths requiring normalized/manual comparison, and 10 were not safely classifiable by the literal extractor. These parser categories are not endpoint-defect counts.

## HTTP foundation contract

- Base URL: `VITE_API_URL`, default `/api`.
- Default headers: `Accept: application/json`, `Content-Type: application/json`.
- Authentication: bearer token from `localStorage` key `iot_token`.
- On `401`, the response interceptor removes the token and rejects the request.
- No global timeout, automatic retry, response-envelope normalization, or error normalization exists in `api.ts`.
- Each service's `.data` or `.data.data` access is the exact current envelope contract.
- Current user and Organization must derive from the authenticated identity. Client `organization_id`, user ID, `global`, `admin`, role, or capability fields never elevate authority.

## Error contract

| Status | Contract |
|---|---|
| 401 | Missing, expired, deactivated, or invalid session; client clears token |
| 403 | Authenticated but currently forbidden |
| 404 | Resource or exact authorized parent/child relation unavailable; avoid tenant disclosure |
| 409 | Revision conflict or coherent workflow race; no overwrite |
| 410 | Expired time-limited workflow, currently invitation-oriented |
| 422 | Safe field validation errors |
| 429 | Technical rate limit, retry-safe where applicable |
| 5xx | Safe unexpected-failure identifier; no stack, SQL, path, or secret |

`RevisionConflict` requires `code`, `message`, resource `{type,id}`, `submittedBaseRevision`, `latestRevision`, `latestContributor`, `latestUpdatedAt`, `changedSections`, `canSaveDraft`, `canReviewChanges`, and `canDiscardAndPull`.

## Authentication and identity contract

B01 is implemented. Laravel Sanctum supplies the one bearer-token mechanism used by `auth.service.ts`: `POST /api/auth/login` returns direct `AuthSession {token,user}`, `GET /api/auth/me` returns a fresh direct User DTO, and `POST /api/auth/logout` revokes the current token and returns 204. Missing, invalid, and revoked tokens produce JSON 401; inactive accounts or Organizations produce 403. Public registration is intentionally unavailable because enrollment is invitation/Admin controlled.

`AuthUserResource` returns only frontend-required `id`, `name`, `email`, `organizationId`, `role`, `platformRole`, and `createdAt`. `LoginRequest` allows credentials and prohibits tenant, role, and status authority fields. `CurrentOrganization` resolves context from the authenticated User relation; a null Organization never means global scope.

`User::productRole()` is canonical: legacy `platform_admin` maps to product Admin, while all other legacy Organization-role values map to product Staff. Those strings remain DTO compatibility only. `isPlatformAdmin()` is a compatibility alias and never platform-wide authority. Invitation acceptance is authenticated, email-bound, expiry-aware (410), single-use, and consumed inside a row-locking transaction.

New conventional lists use `page` and server-bounded `per_page` (default 25, maximum 100 unless a domain is stricter) with Laravel-compatible `data`, `current_page`, `per_page`, `last_page`, and `total`. Authorization and eligibility must occur in the query before effective pagination. Existing envelopes stay unchanged until their owning frontend-driven slice.

## Authorization invariants

- Staff Device authority comes only from the Device assignment relation; same Organization is insufficient.
- Full Access is not Admin. Atomic creator Full Access is allowed only inside new Device creation.
- Creator, Contributor, Comment, Mention, Notification, ShareRequest, review ownership, publication, and reference relationships do not authorize.
- Generic private grants are direct View/Edit where explicitly adopted.
- `/app/*` is personal. `/admin/*` is Admin-global only within the authenticated Admin's current Organization.
- Sharing operates on the original resource. No copies, forks, branches, merge model, CRDT, OT, universal ACL, or Owner permission abstraction.
- Dashboard access never grants protected source access. Device and Location access do not transfer between domains.
- Cache and queued payloads are never authority; revalidate current state.

## Device foundation

B02 is implemented against `device.service.ts` and `types/device.ts`. Staff Device authority comes only from `device_access_assignments`; its unique `(device_id,user_id)` relation stores `viewer` or `full_access` plus `assigned_by` audit provenance. `DeviceAccessService` owns list qualification, detail resolution, capability calculation, assignment integrity, downgrade, and revoke. Same-Organization membership, creator attribution, generic `resource_collaborators`, references, and pending sharing state cannot authorize a Device.

`GET /api/devices` remains the direct Device-array envelope consumed by the frontend and is assignment-qualified in SQL before retrieval. `GET /api/devices/{device}` rechecks current access. Viewer is read-only; Full Access enables the currently supported Device management capabilities but never changes the Staff account role or enables Admin routes. Admin access is independent of assignment but is limited to the authenticated Admin's current Organization.

`DeviceResource` is the ordinary safe DTO boundary. It returns the frontend-required identity, metadata, safe Location/Template/creator references, current access presentation, and backend-derived capabilities. It excludes raw ORM names, assignment rows, credentials, tokens, secrets, private paths, and unrelated Organization configuration. Runtime summary fields (`status`, `lastSeen`, battery, firmware version) remain live and are not revision authority. Capabilities are presentation only; endpoints reauthorize.

`DeviceCreationService` requires the actor's active, server-derived current Organization even when invoked below HTTP middleware. Staff creation places the new Device and exactly one creator `full_access` assignment in the same transaction, with `assigned_by` set to the creator. This is creation-scoped and is not a general self-assignment rule. Admin creation creates no Staff assignment. Location and Template relations are independently resolved within the Organization; any failure rolls back Device and assignment.

Revocation deletes the canonical assignment and removes direct detail/list access on the next request. Downgrade from `full_access` to `viewer` retains read access and removes management capabilities immediately. No Device assignment schema migration was required.

## Frontend route to backend capability map

| Frontend route group | Context | Required backend capability |
|---|---|---|
| `/login`, `/invite/:token` | Public/session | Login, invitation state/acceptance, safe expiry |
| `/app/dashboard` | Personal | Current-user monitored Device/dashboard summary |
| `/app/collaboration`, `/my-work`, `/shared-with-me`, `/changes`, `/search`, `/activity`, `/notifications` | Personal | Authorized, paginated projections and bounded Overview providers |
| `/app/devices/*` | Personal | Assignment-authorized Device config/runtime/security child APIs |
| `/app/dashboard/*` | Private/published | Dashboard CRUD, sources, sharing, revisions, Draft, review/publication |
| `/app/reports/*`, `/automations/*`, `/locations/*`, `/developer/*` | Personal | Explicit domain contracts; no automatic generic adoption |
| `/admin/*` | Admin current Organization | Explicit organization-global governance, never platform-wide client elevation |

Protected direct routes require fresh backend authorization. Denied and absent relations must resolve coherently as 401/403/404, not stale cached success.

## Domain capability matrix

| Domain | Access | Revision/Safe Save | Draft/presence | Presentation/Pull | Collaboration/projections | Review/publication/lifecycle | Runtime/secrets/files |
|---|---|---|---|---|---|---|---|
| Device | Assignment Viewer/Full Access | Metadata/config writers only | Supported where UI adopts | Explicit policy; Device Dashboard distinct | Comments, Activity, Search, personal projections | Lifecycle; no creation review | Telemetry, commands, credentials live and protected |
| Device Dashboard | Through Device plus independent source auth | Yes | Yes/presence | Pull-managed | Comments, Activity, Changes | Review/publication where exposed | Widget source runtime live |
| Independent Dashboard | Direct View/Edit | Yes | Yes/presence | Current explicit immediate policy | Sharing, Comments, Search, projections | Review/publication/lifecycle | Each source independently authorized |
| Template | Direct View/Edit | Yes, including Template Web Dashboard | UI/domain adoption | Explicit current policy | Sharing, Comments, Activity, Search | Review/publication/lifecycle | Canonical Datastream definitions; no runtime secrets in snapshots |

Template Datastreams use `DeviceTemplateParameter` as the canonical reusable schema. Supported types are `number`, `integer`, `string`, `boolean`, and `enum`; optional semantic, unit, default, range/precision/step, length, labels, and Enum options are explicit configuration. Applying a Template copies a coherent definition snapshot to `DeviceParameter`; Devices then supply live values through telemetry. Both user and Device-credential ingestion validate known keys and values through the same schema service. Nonnumeric typed values are retained separately from the legacy numeric analytics column, and definitions never contain runtime credentials or live telemetry.
| Automation | Direct View/Edit | Yes | Yes/presence | Explicit current policy | Sharing/projections | Review/publication/lifecycle where exposed | Executions and credentials live/separate |
| Report | Direct View/Edit | Yes | UI/domain adoption | Explicit current policy | Sharing/projections | Review/publication/lifecycle where exposed | Runs/outputs separately authorized |
| Webhook | Direct View/Edit | Yes | Yes where exposed | Explicit; not publication | Sharing/projections | Activation review separate; lifecycle | Signing secret write-only; deliveries runtime |
| Location | Direct View/Edit | Yes | Yes/presence | Immediate; **NO Pull** | Comments, Activity, Search | Lifecycle | Device references do not grant Device access |
| Firmware | Direct safe metadata access | Safe metadata only | Not inferred | Explicit current policy | Search/projections where exposed | Release review; not OTA proof | Binary/download/deployment separately authorized |
| Integration UI | Unresolved compatibility surface | Not established | Not established | Not established | Not automatically adopted | Not established | Do not build aggregate endpoints from route existence |

## Device contract

Device services require list, detail, create, update, delete, parameters, Device Dashboard, credentials/security, sharing/access, commands, telemetry/analytics, health, snapshots, crash reports, provisioning, onboarding, fleet, and Admin views. Classify every mutation as configuration Safe Save, live runtime, security/access, lifecycle, or file action; do not revision every Device write.

Creation accepts `name`, `type`, `serialNumber`, `protocol`, optional `location_id`, `macAddress`, and `template_id`. Staff creation must transact Device plus creator Full Access assignment. Reads and mutations return backend-derived `access` and `capabilities`; those DTO fields are presentation, not authority.

## Safe Save and idempotency

Known writers include Device update, Dashboard update (including Device Dashboard), Automation update, Template update, Report update, Webhook update, Location update, Firmware safe metadata update, and Draft Apply where their current service sends a base revision/idempotency key. Each must be verified against its exact service signature before its implementation sprint.

The pipeline is authenticate → tenant/resource resolve → authorize/lifecycle check → base check → transaction/lock/revalidate → scoped idempotency → safe mutation → deterministic secret-free snapshot → no-op detection → at most one immutable Revision → commit → duplicate-safe effects. Same-key/different-command rejects; ambiguous retry replays; stale independent commands return 409. No force save, automatic merge, or branch.

## Draft and editing presence

`draft.service.ts` requires get, save, discard, and apply using resource type/ID and `ResourceDraft`. Draft is current-user private working state, does not mutate canonical state, and Apply uses Safe Save. Revoke and lifecycle are rechecked.

`editing-presence.service.ts` requires start, heartbeat, list, and release. `useEditingPresence` is the shared consumer. Presence is advisory, TTL-bound, non-locking, and non-authoritative.

## Sharing, Comments, and Notifications

Device sharing requires request → recipient response → Admin approval before assignment authority. Generic Dashboard sharing currently exposes candidates, grant creation, list, permission change, and revoke. Revoke/downgrade must propagate to direct URLs and projections.

Comments require thread list/create, reply, acknowledge, resolve, and reopen with trusted parent/anchor validation. Mentions notify only an authorized audience and never authorize.

Notifications require list/recent/unread count, read/unread/all-read, dismiss/restore, and profile notification settings. Read, dismissed, and actionable states are independent. Materialization is fact-driven, post-commit/recoverable, deduplicated, and actions reauthorize current state.

B04 is implemented. Device sharing uses `ResourceShareRequest` as workflow state only: Staff Full Access may request, the recipient may accept or decline, and final Admin approval creates or updates the canonical `device_access_assignments` row. Pending and recipient-accepted requests never authorize. Approval locks the request and assignment, preserves an existing stronger `full_access` assignment, revalidates Device/recipient tenancy and eligibility, and cannot escalate beyond the requested level. Explicit Device downgrade/revoke remains in the canonical Device access service.

Generic sharing preserves the original resource and materializes direct `view`/`edit` rows in `resource_collaborators`. Active workflows currently cover Device Template, independent personal Dashboard, Automation, Report, and Location in addition to specialized Device governance. Revocation and downgrade update the canonical grant directly. No copy, clone, fork, branch, or referenced-resource grant is created.

Comments use `CollaborationThread` and `CollaborationComment`. Every list/create/reply/edit/acknowledge/resolve/reopen operation re-resolves current parent authorization. Anchors use allowlisted resource/section/parameter/Dashboard-widget/Location metadata forms and exact resource/child/Revision relations. Mention candidates are active, current-Organization users who already have parent access; a mention creates a notification fact but no grant.

Notification producers record bounded facts through `NotificationOutboxService`. Jobs carry the outbox identifier, materialization is retryable and deduplicated, and duplicate processing preserves read, dismissed, and action state. Recipient identity and Organization are server-controlled and must match. Notification payloads remain presentation only and never authorize their target.

## Pull, Changes, Search, Activity, and personal projections

- Pull/Changes: server-paginated current-authorized behind resources, personal accepted pointer, ignore/reminder, and Pull cleanup. Only explicit pull-managed domains participate; Location never does.
- Search: GET contract through `search.service.ts`, trusted resource/filter vocabulary, server ranking/pagination, current authorization, safe presentation, secret/runtime exclusion.
- Activity: global and resource paths plus metadata; authorize before effective pagination and exclude operational runtime feeds.
- My Work: current authorized factual creator/contributor/Draft projection, not tasks or authority.
- Shared With Me: current direct assignments/grants only.
- Overview: one bounded personal response composing canonical counts/previews without side effects.

B05 is implemented as current-authorized personal read models. `ResourceUpdatePolicyRegistry` explicitly classifies Device as Pull-managed for its safe Dashboard presentation section; independent Dashboard, Device Template, Automation, Report, and Webhook as immediate; Location as unsafe to pin with no Pull; and Firmware as live-only. `resource_revision_states` stores only a per-user accepted presentation pointer. It is neither authorization nor a resource copy.

Changes queries assignment-authorized, active Devices before pagination and returns only genuinely behind Pull-managed presentation states. Pull re-resolves current access, locks the latest Revision and the current user's state, advances only that accepted pointer, and clears obsolete Ignore/reminder/Notification action state. It never changes configuration, assignments, grants, lifecycle, review, publication, runtime, or another user's pointer.

`CrossDomainSearchService` uses an explicit safe corpus and query-level Device assignments/direct generic grants. It returns one top-level resource row and searches safe identity/metadata or parent-scoped child metadata; Device Dashboard widget matches use the user's accepted snapshot. Private destinations reauthorize, while published Dashboard search uses the exact publication-version Revision.

`CollaborationActivityService` composes committed collaboration facts only. Candidate queries are constrained by current canonical access before bounded pagination; telemetry, operational events, executions, deliveries, and other runtime logs are excluded. Historical participation has no visibility fallback.

`MyWorkService` combines current authorization with creator, human Revision contributor, or current-user Draft facts. `SharedWithMeService` reads only current Device assignments and direct generic grants. `CollaborationOverviewService` composes bounded previews and matching provider counts for My Work, Shared With Me, Changes, unread Notifications, Activity, and Search. These projections are read-only, personal `/app/*` context—not tasks, analytics, employee scoring, or Dashboard resources.

## Review, publication, and lifecycle

Admin and Dashboard review services require queue/summary/detail, claim, release, confirmed takeover, approve, request changes, and reject. Submission pins an exact Revision; ownership coordinates reviewers only; race-safe terminal decisions apply to that exact Revision.

Dashboard, Template, Automation, Report, and actual supported publication services must return immutable published presentations tied to the approved Revision. Webhook activation and Firmware release are distinct domain effects. Lifecycle is separate from runtime/review/publication/access; restore does not regrant, republish, reactivate, Pull, or apply Draft.

## Runtime and file boundary

Telemetry, analytics, commands, heartbeats, Automation executions/logs, Webhook deliveries, Report runs/outputs, Firmware artifacts/deployments, crash reports, operational events, provisioning, and credential rotation are live/protected APIs. File download authorizes the exact parent/file relation. Secrets never enter generic DTOs, Revision, Draft, Search, Activity, Notification, review, publication, logs, caches, or ordinary job payloads.

## Admin contract

Current Admin UI consumes Overview, Device/Location views, assignment governance, access requests, review queues, inventory/metadata/detail/viewed state, recently created/updated, disabled resources, Needs Attention, users, Organizations, monitored Devices, and system settings. Every query must scope to the authenticated Admin's current Organization. Current legacy platform-global vocabulary is a contract defect to resolve in B01, not permission to implement cross-Organization authority.

## Unused / legacy frontend API contracts

The following 54 exported functions have no current non-service TypeScript consumer and must not automatically create B-sprint scope:

`getResourceActivity`, `updateGlobalDevice`, `getDeviceAccessForDevice`, `getDeviceAccessForUser`, `listMyAttention`, `getAuditLogs`, `getAuditLog`, `getAutomationLogs`, `getAutomationLog`, `getTemplates`, `createAutomationFromTemplate`, `createTemplate`, `getCommandHistory`, `getAdminCrashReport`, `releaseDashboardReview`, `getDashboards`, `deleteDashboard`, `duplicateDashboard`, `getGroups`, `createGroup`, `addDevicesToGroup`, `sendHeartbeat`, `getDeviceHealth`, `deviceRevisionBase`, `getDeviceCertificates`, `getDeviceTokens`, `revokeCredential`, `getTemplatePublicationVersions`, `getTemplatePublicationVersion`, `updateFirmware`, `submitFirmwareRelease`, `getFirmwareReleaseState`, `beginIdempotentSave`, `getMembers`, `getInvitations`, `cancelInvitation`, `generateDeviceClaim`, `claimDevice`, `getOperationalEvent`, `listAdminOperationalEvents`, `createOrganization`, `getOrganizationSettings`, `updateOrganizationSettings`, `getProfile`, `updateReport`, `getReportRun`, `createSchedule`, `getSchedules`, `deleteSchedule`, `saveToken`, `sendTriggerEvent`, `getTriggerEvents`, `getWorkflows`, `createWorkflow`.

Some may be test/indirect/compatibility contracts; each requires a call-site decision before removal or backend implementation.

## Unresolved contract decisions

| Domain | Decision required | Recommendation/risk |
|---|---|---|
| Identity | Legacy `platform_admin` maps to Admin; other compatibility roles map to Staff | Implemented in B01; never Owner/platform-global authority |
| Envelopes | Mixed direct `.data` versus `.data.data` | Freeze per-function contracts or coordinate normalization |
| Dynamic routes | Normalize 167 interpolated/helper paths against Laravel | Complete before relevant domain sprint; do not call them missing |
| Unused functions | Compatibility, indirect use, or removal | Decide per function before scheduling endpoint work |
| Integration UI | Whether any aggregate backend domain is real | Treat as UI compatibility until explicit product decision |
| Domain policies | Exact Draft/review/publication/presentation coverage where UI evidence is partial | Resolve before that domain sprint; Location remains no Pull |
| Registration | Production self-registration policy | Default to controlled company enrollment unless explicitly approved |

## Existing backend disposition

Existing Laravel code is an implementation candidate, not product authority. Compare each frontend requirement and classify existing behavior as **CONFORMING — REUSE**, **ADAPT**, **HARDEN**, **REPLACE**, or **IGNORE/REMOVE**. Never modify frontend requirements merely to legitimize convenient existing backend behavior, and do not rebuild a conforming subsystem merely because it predates this guide.

## Dependency graph and roadmap

The recommended backend build order is HTTP/auth/current Organization → Device assignments and Device DTOs → generic domain authorization/DTO registry → Safe Save/Revisions/idempotency → Draft/presence → sharing/comments/mentions → Notifications/jobs → Pull/Changes/personal projections → Search/Activity/Overview → review/publication/lifecycle → Dashboard sources/runtime/files → Admin/external hardening and integrated acceptance.

B01 through B12 are complete. The backend implementation is ready for final system/deployment validation, subject to the external validation gaps recorded in `BACKEND_VALIDATION.md`.

## B06 review, publication, and lifecycle implementation

Review submissions pin one immutable `submitted_revision_id`. Reviewer claim, release, and confirmed takeover coordinate work but never authorize the private resource. Every ownership transition and terminal decision locks and reloads the submission, requires an active current-Organization Admin, verifies the exact domain resource belongs to that Organization, verifies the submitted Revision belongs to that resource, and rejects stale or terminal transitions.

Approval creates at most one immutable `ResourcePublicationVersion` for the exact approved Revision. Device Template, independent Dashboard, Automation, and Report use versioned publication. A newer draft does not replace the published payload. Webhook activation and Firmware release remain separate governance effects and are not publication or delivery/OTA proof.

Lifecycle state remains independent of authorization, review, publication, runtime, Draft, and Pull. Disable blocks unsafe mutations and deactivates applicable Webhook/Automation runtime switches; restore does not regrant, republish, reactivate, Pull, apply Draft, or deploy Firmware. Archive remains a separate terminal governance action where supported.

Legacy domain review list/detail surfaces and canonical Review Center are current-Organization scoped. A foreign submission identifier returns 404 even for an Admin, and client Organization selectors cannot broaden the queue.

## B07 Notification and background reliability implementation

`NotificationOutboxEvent` is the single durable fact/materialization queue. Domain transactions record a bounded fact identity and dispatch `ProcessNotificationOutboxEvent` only after commit. The scheduled `notifications:process-outbox` recovery command redispatches eligible unfinished events. Jobs carry the outbox event ID, reload state, use five bounded attempts with 30/120/600-second backoff and a 30-second timeout, and mark permanent failure without replaying the originating domain command.

`NotificationMaterializationService` validates an allowlisted rule, active recipient, current recipient Organization, preferences, safe attributes, and a database-unique fact/rule/recipient deduplication key. Reprocessing uses create-once semantics and preserves read, dismissed, and completed action state. Current-Organization Admin recipients are resolved from the resource Organization; foreign Admins are excluded.

Notifications are personal presentation records. List, recent, unread count, read/unread, mark-all-read, dismiss/restore, and preferences are scoped to the authenticated recipient. `NotificationResource` is the safe frontend DTO. Action/deep-link resolution reloads the share, revision, publication submission, and underlying resource as applicable; it rechecks recipient/workflow/Organization state and turns stale, revoked, foreign, unknown, or unsafe-external destinations into non-actionable output. Notification membership never grants target access.

## B08 Pull, Changes, and personal projections

`ResourceUpdatePolicyRegistry` is the single trusted presentation-policy source. Device Dashboard safe presentation is Pull-managed; independent Dashboard, Device Template, Automation, Report, and Webhook are immediate; Firmware is live-only; Location is unsafe to pin and has no Pull. Clients cannot select or expand policy.

`resource_revision_states` stores one per-user accepted Revision pointer for Pull-managed Device presentation. Grant/regrant initialization starts at the current Revision. Pull tenant-scopes and authorizes the Device, requires active lifecycle, locks latest Revision and the user's pointer, rejects Draft conflict and invalid/backward state, advances only that pointer, and clears obsolete reminder/Notification state. It does not mutate configuration, Revisions, access, lifecycle, review, publication, runtime, or another user.

Changes is an assignment-authorized, active, behind-only, one-row-per-Device SQL projection before pagination. Ignore records the current generation and defers its Notification without accepting the Revision; the row remains available for deliberate Pull under the current frontend contract, and newer revisions supersede the ignored generation. Reminders reload authorization, lifecycle generation, and still-behind state before deduplicated materialization.

My Work combines current authorization with factual creator, human Revision contributor, or current-user Draft relationships. Shared With Me reads only current direct Device assignments and generic View/Edit grants. Both are tenant-scoped, deduplicated, bounded, immediately reflect revoke/downgrade, and never provide authority or employee-productivity data.

## B09 Search, Activity, and Collaboration Overview

`CrossDomainSearchService` is the single cross-domain Search engine. Its explicit safe corpus covers Device, Device Template, independent Dashboard, Automation, Report, Location, Firmware metadata, and Webhook safe identity. Device rows require canonical Viewer/Full Access assignment; generic rows require current View/Edit grants; independent Dashboard also recognizes its canonical owner authority without requiring a redundant grant. Published Dashboard discovery is separate and reads the exact pinned publication Revision. Search composes authorized SQL providers before pagination, returns one top-level resource row, escapes literal wildcard input, builds trusted internal destinations, and excludes secrets, Drafts, raw snapshots, runtime payloads, and storage paths.

`CollaborationActivityService` composes canonical Revision, Comment/thread-transition, sharing, review, and publication facts only. It calculates current resource eligibility first, bounds each factual provider, merges with stable ordering, and emits safe actors/resource identities/section metadata. Telemetry, heartbeat, command, execution, delivery, report-run, deployment, notification-state, navigation, and presence events are excluded. Historical creator/contributor/comment/reviewer participation does not preserve visibility after revoke.

`CollaborationOverviewService` is a fixed personal read-only composition, not a Dashboard resource or persistent snapshot. It reuses My Work, Shared With Me, Changes, Notification, and personal Activity providers, returns bounded previews with provider-derived counts where defined, supplies a Search destination, and performs no read-state, Pull, Ignore, reminder, review, attribution, or persistence mutation. Admin `/app/*` behavior remains personal.

## B10 governance completion

B10 revalidated the existing Review, Publication, Webhook activation, Firmware release, and Lifecycle implementation as the canonical frontend-driven governance stack. Review submissions and terminal decisions remain pinned to the exact immutable submitted Revision; versioned publication remains pinned to the exact approved Revision; lifecycle remains separate from authorization, runtime, Draft, Pull, review, and publication.

Every governance detail, history, claim, release, takeover, and terminal-decision path now independently proves the underlying resource belongs to the authenticated Admin's current Organization. This includes Webhook and Firmware paths and remains true if a stale or corrupt reviewer link points at a foreign Admin. Firmware release-submission Notifications are limited to active Admins in the Firmware resource's Organization. Webhook review history is supported through the same safe, tenant-checked history boundary as the other reviewable domains.

No frontend contract, schema, publication model, lifecycle model, delivery behavior, or OTA behavior changed in B10.

## B11 Dashboard sources, runtime, and protected files

Dashboard configuration and live source reads remain separate. Every Device-backed widget validates the referenced Device independently through canonical Device assignments/current-Organization Admin authority. Dashboard access never transfers Device authority. Source authorization is batched for Dashboard serialization; unavailable sources retain safe widget presentation while removing the protected datasource reference.

Dashboard widget types are resolved through `DashboardWidgetRegistry`; clients cannot submit arbitrary renderer classes. The canonical palette contains 19 core widget types while legacy saved widget types remain readable. Configuration is allowlisted and stored through the existing Safe Save/Revision path. Event widgets consume authorization-qualified operational events, activations consume completed provisioning sessions, and Device/telemetry widgets reauthorize their sources at the underlying endpoints. Protected floorplan assets use the `dashboard_map_assets` domain and are reauthorized on every read. Map projection returns only valid coordinates from currently authorized Devices and Locations; it never uses mock coordinates, public storage paths, or arbitrary image URLs.

Telemetry, analytics, health, commands, live parameters, credentials/provisioning, snapshots/crash reports, Automation executions, Report runs, Webhook deliveries, and Firmware deployments remain live operational state rather than Revision, Draft, Pull, Search, or Activity content. Runtime queries authorize before aggregation/pagination, bound inputs and results, and revalidate current source access where a protected reference is involved.

Automation execution history requires both current Automation View/Edit authority and current authorization for its referenced Device. Organization membership or Device access alone is insufficient, and revoking either relationship removes runtime-history access immediately. System-triggered execution remains separate system authority and does not create a user grant.

Report and Firmware downloads resolve trusted records through their exact authorized parent relationship. Storage paths are generated server-side and excluded from ordinary DTOs. Firmware uploads enforce bounded size and allowlisted extensions, store under generated Organization-scoped keys, sanitize client filenames, and derive checksum/size/MIME server-side. Webhook delivery pins approved provenance, rejects redirects and unsafe destinations, uses bounded timeouts/backoff, and never exposes signing secrets.

No frontend contract or database schema changed in B11.

## B12 final integrated backend status

Every active `/admin/*` workspace is current-Organization governance. The legacy “global” names mean Organization-wide visibility, never platform-wide visibility. Admin Device inventory/creation/detail/update, Device Template operations, Locations, Device assignments/options, credentials, monitored Devices, crash reports, provisioning sessions, operational events, resource inventory, disabled resources, recent resources, and Review Center all derive their tenant from the authenticated Admin. Client `organization_id` filters may remain accepted for frontend compatibility but cannot expand scope.

B12 removed the remaining platform-global behavior in legacy Admin Device, operations, template, Location, credential, assignment, monitoring, crash, provisioning, and operational-event paths. Foreign IDs return 404 and stale route-model bindings do not bypass tenant checks. Existing current-Organization services for resource inventory, recent/disabled resources, Review, publication, sharing, and user governance remain canonical.

The active frontend/backend contract, authorization, DTO, pagination, secret, cache, job, idempotency, file, SSRF, lifecycle, review/publication, runtime, and cross-tenant suites pass on the repository test environment. No frontend contract or database schema changed in B12.

## Trusted generic resource foundation

`CollaborationResourceRegistry` is the single explicit server allowlist. It registers Device (identity/cross-domain resolution only), Device Template, independent personal Dashboard, Automation, Report, Webhook, Location, and Firmware. Integration remains a frontend-compatibility surface and is not registered. Unknown keys, PHP class names, table names, and unsupported abilities fail closed.

Resolution requires an active authenticated user, derives the Organization from that user, tenant-scopes the model query before authorization, and delegates View/Edit/Administer decisions to the domain adapter. Admin access remains current-Organization only. Non-Device grants use `resource_collaborators` with `view`/`edit`; Device never uses that table as authority. Registration never auto-enables a collaboration capability.

| Resource key | Real implementation | Private authority | Safe DTO boundary | Presentation policy |
|---|---|---|---|---|
| `device` | `Device` | Assignment Viewer/Full Access only | `DeviceResource` | Pull-managed where explicitly supported |
| `device_template` | `DeviceTemplate` | Direct View/Edit; current-org Admin | `DeviceTemplateResource` | Pull-managed |
| `dashboard` | Independent personal `Dashboard` | Canonical Dashboard owner/direct View/Edit | Dashboard service DTO | Immediate; no Pull |
| `automation` | `Automation` | Direct View/Edit; current-org Admin | Automation service DTO | Pull-managed |
| `report` | `Report` | Direct View/Edit; current-org Admin | `ReportResource` | Immediate |
| `webhook` | `Webhook` | Direct View/Edit; current-org Admin | `WebhookResource` | Pull-managed; secret excluded |
| `location` | `Location` | Direct View/Edit; current-org Admin | Location service DTO | Immediate; **NO Pull** |
| `firmware` | `FirmwareArtifact` | Direct View/Edit; current-org Admin | `FirmwareArtifactResource` | Pull-managed metadata; storage path excluded |

Capabilities are backend-derived presentation data and are rechecked at every authoritative endpoint. Exact-child APIs resolve children through the authorized parent relation. References never transfer authority: Dashboard-to-Device, Automation-to-Device, Report-to-Device, Device-to-Location, and Location-to-Device remain independently authorized.

## Backend development workflow

For each frontend area: identify the routed page and rendered states; trace imported service functions and types; record method/path/request/response/errors; separate unused functions; define server-owned security and transaction rules; compare existing Laravel behavior only afterward; implement the smallest vertical slice; test it through both backend contracts and the real frontend; then update these canonical documents.

Backend completion is measured by frontend workflow support plus server-side correctness, not by the number of controllers, models, or CRUD endpoints created.

## Maintenance rule

Update this file for backend architecture and contract changes. Update `BACKEND_STANDARDS.md` for engineering rules and `BACKEND_VALIDATION.md` for fresh evidence. Do not create feature-, sprint-, audit-, or matrix-specific backend Markdown files.
