# Backend Standards

## Parameter and telemetry schemas

- Template parameters are the source for reusable Device data definitions; do not introduce a parallel Datastream schema.
- Data type and key are immutable after creation. Type-specific configuration must be allowlisted and validated before persistence.
- Template-driven Devices reject unknown telemetry keys and invalid values. Device credentials determine the Device identity; payload Device or Organization identifiers are not authority.
- Parameter DTOs may expose safe definitions and latest authorized values, but never credentials, raw storage details, or unrelated telemetry payloads.
- Widgets bind to authorized Device parameters and apply type compatibility; a typed client selection never replaces endpoint authorization or ingestion validation.

## Contract first

Inspect the consuming frontend service, types, pages/hooks, and browser workflow before changing an endpoint. Match its method, path, parameters, headers, body, envelope, DTO casing, pagination, and error states. Do not implement unused service functions without an explicit compatibility decision.

The frontend defines required behavior; existing backend routes, controllers, models, migrations, and conventions do not define product requirements. Use existing backend code only when it conforms to the verified frontend contract and server-owned security invariants. Do not create generic CRUD first and connect the UI afterward; build vertical frontend-driven slices.

## Authentication, tenant, and authorization

- Use Sanctum bearer tokens for the current frontend; do not introduce parallel authentication.
- Serialize login/current-user identity through `AuthUserResource`, never a raw User model.
- Run `auth:sanctum` then `active` on protected routes. Any User status other than `active`, or attached Organization status other than `active`, denies access.
- Resolve tenant context through `CurrentOrganization`. A missing Organization is never global authority.
- Resolve product authority through `User::productRole()`: Admin or Staff only. `platform_admin` is compatibility for Organization-scoped Admin, not a third role or platform bypass.
- Derive actor and current Organization from the authenticated session.
- Enforce active-account, tenant, exact resource, exact child relation, lifecycle, and capability on every request.
- Never trust client role, tenant, user, capability, presentation, or cached state as authority.
- Preserve canonical Device assignments and domain-specific direct grants. Do not create a universal ACL or Owner permission abstraction.
- References, attribution, workflow, publication, and notifications never imply private access.

## Controllers, services, and DTOs

Controllers validate transport and delegate. Domain services own transactions and invariants. Policies/authorizers remain explicit per domain. Return safe purpose-built DTOs rather than ORM models. Keep response envelopes stable per consuming service until a coordinated contract migration.

## Device authority

- Staff Device authority is only the current `device_access_assignments` row with `viewer` or `full_access`.
- Same Organization, creator/contributor attribution, generic grants, comments, mentions, notifications, sharing requests, dashboards, Locations, Templates, review, publication, and references never grant Device access.
- Full Access remains Staff and never permits Admin routes. Admin Device authority is assignment-independent but current-Organization only.
- Qualify Staff lists through assignments in SQL and reauthorize every detail/mutation. Cached DTO capabilities are never authority.
- Serialize ordinary Device responses through `DeviceResource`; never expose raw assignment relations, credentials, secrets, storage paths, or ORM fields.
- Create a Staff Device and exactly one creator `full_access` assignment in one transaction. The assignment records creator provenance and cannot be generalized into self-assignment.
- Validate Location/Template/child relations through the exact current-Organization parent relation. References never transfer authority.
- Revoke and downgrade take effect on the next authoritative request. Do not preserve access from cache or historical attribution.

## Generic resource registry and grants

- Resolve collaborative resource types only through the explicit `CollaborationResourceRegistry`; never accept a PHP class, morph class, table, namespace, policy, or serializer from a client.
- Require an active user and tenant-scope the resource query to the server-derived current Organization before invoking its domain authorizer. Admin never bypasses this boundary.
- Use domain adapters for View, Edit, Share, Comment, Review, and Administer decisions. Do not create a universal ACL or infer capabilities merely because a type is registered.
- Generic private grants are direct `view`/`edit` relations only. Edit implies View; View never implies mutation. Device is excluded and remains assignment-authorized.
- Creator, contributor, comment, mention, notification, ShareRequest, review ownership, publication, and references are not fallback authority. Any accepted domain creation rule must materialize its canonical grant transactionally.
- Resolve a child through its authorized parent relation or explicitly verify that relation. A globally found child ID is never sufficient.
- Serialize explicit safe DTOs. Never expose ORM internals, model classes, authorization internals, Webhook signing secrets, Firmware storage paths, or protected referenced-resource data.
- Treat capability DTOs as presentation hints only and reauthorize mutations. Location is immediate and never Pull-managed.

## Sharing, comments, mentions, and notifications

- Device share requests and recipient acceptance are workflow facts, not authority. Only final current-Organization Admin approval may create/update the canonical Device assignment.
- Lock and revalidate share transitions. Preserve stronger existing access; require a separate explicit operation for downgrade or revoke.
- Generic sharing grants the same original resource through its canonical `view`/`edit` row. Never create copies, forks, branches, transitive grants, or a second permission source.
- Reauthorize the parent for every Comment/thread operation. Validate thread, reply, child, section, and Revision anchors against their exact parent relation.
- Store Comment bodies as untrusted plain text. Mention only active current-Organization users with current parent access. Comments and mentions never create access.
- Record notification facts in the existing outbox inside the owning transaction and dispatch processing after commit. Jobs carry identifiers, not authority or secret-rich domain snapshots.
- Materialization must be recoverable and logically deduplicated. Retry must preserve read, dismissed, and action state.
- Derive Notification recipients and Organization server-side, require them to match, and reauthorize current target/workflow state when an action is attempted.
- Sharing, access, Comments, mentions, and Notification interaction state are live collaboration data—not resource configuration Revisions, Drafts, or Pull state.

## Notification and background reliability

- Materialize Notifications only from committed recoverable facts. Rollback leaves no success Notification, and materializer failure does not require replay of the user command.
- One fact/rule/recipient/channel identity produces at most one row under a database uniqueness constraint. Retry never resets read, dismissed, or action-complete state.
- Resolve recipients and Admin recipients from current server state and the resource's current Organization. Never broadcast to platform-wide or historical recipients.
- Treat Notification actions as trusted pointers only: reload the target, recheck current Organization, authorization, lifecycle, and workflow state, and fail closed for stale or unsafe destinations.
- Notification jobs carry stable identifiers, reload current records, are idempotent, and define bounded retries, backoff, timeout, and permanent-failure handling. Never serialize ORM authority, cached capability, secrets, Drafts, raw configuration, telemetry, or credentials.
- Do not perform external HTTP inside the canonical domain transaction. Cache, outbox payloads, and jobs are never authorization.

## Personal projections and Pull

- Ignore defers the current change-generation Notification but does not accept the Revision. Under the current frontend contract the Changes row remains available for an explicit Pull; a newer Revision supersedes the ignored generation.
- Initialize and reinitialize newly granted Pull-managed presentation state at the current valid Revision so grant/regrant does not resurrect an obsolete personal pointer.
- Changes, My Work, and Shared With Me must constrain current canonical authorization and Organization in SQL before effective pagination and counting, with one row per resource.
- My Work requires current access plus creator, human contributor, or current-user Draft fact. Shared With Me requires only a current direct Device assignment or generic View/Edit grant. Neither projection is authority, task ownership, or employee analytics.

## Search, Activity, and Overview

- Search only explicit trusted domains and safe fields. Apply current canonical authorization and Organization scope in provider queries before pagination, deduplicate to one top-level resource, and generate destinations from trusted routing rules.
- Device Search accepts only Viewer/Full Access assignments. Generic Search accepts only View/Edit grants; independent Dashboard owner authority is its explicit domain rule, not a generic creator fallback.
- Use the current user's accepted presentation for Pull-managed searchable content, current canonical state for immediate domains, safe metadata for live-only domains, and exact publication Revisions for published discovery.
- Activity contains committed collaboration facts only. Exclude telemetry, runtime, HTTP/navigation, Notification interaction, presence, and employee-productivity events.
- Resolve Activity authorization before effective pagination. Historical participation never preserves private Activity access after revoke.
- Section Activity requires an allowlisted semantic section for an exact trusted and currently authorized resource. Never expose arbitrary columns, relations, classes, tables, or raw fact metadata.
- Collaboration Overview is fixed, personal, bounded, read-only provider composition. Counts come from the same canonical providers as previews; rendering causes no mutations or persistent Dashboard/snapshot.

Review/publication/lifecycle rules:

- A review submission always identifies one exact immutable Revision of one trusted resource. Reviewer ownership is coordination only and never grants resource access.
- Claim, release, takeover, and terminal decisions lock and reload state and verify current-Organization Admin authority, exact resource tenancy, exact submitted-Revision relation, active lifecycle, expected reviewer, and actionable status.
- Takeover requires explicit confirmation. Only the current reviewer may decide. A terminal decision clears ownership and cannot produce a second result.
- Publication versions are immutable and point to the exact approved Revision. Webhook activation is not publication; Firmware release approval is not deployment or OTA evidence.
- Lifecycle is separate from grants, review, publication, runtime, Draft, and Pull. Restore never regrants, republishes, reactivates, Pulls, applies Draft, or triggers deployment.
- Review queues, legacy domain review lists, detail, history, and mutations derive Organization scope from the authenticated Admin. Client tenant filters are never authority.
- Review detail/history and terminal decisions must resolve the underlying domain resource in the Admin's current Organization even when the submission type and reviewer fields look valid. Reviewer ownership is never a substitute for tenant authorization.
- Governance Notifications select recipients from the resource Organization. Never broadcast review or release facts to Admins in other Organizations.

- Every projection qualifies current canonical authorization in its query before effective pagination. A Search, Activity, My Work, Shared With Me, Changes, or Overview row never grants access.
- Define presentation behavior only in the trusted server policy registry. Location never supports Pull.
- Accepted Revision state is a personal presentation pointer—not configuration, authorization, a copy, a fork, or a branch. Pull changes only the current user's validated forward pointer.
- Pull must reauthorize the resource and must not mutate canonical configuration, latest Revision, grants, assignments, lifecycle, review, publication, runtime, secrets, or another user's state.
- Search uses an allowlisted safe corpus, bounded server pagination, and one result per top-level resource. Never search secrets, raw snapshots, Draft contents, telemetry, or runtime payloads.
- Activity contains committed collaboration facts only, never telemetry or operational/runtime logs. Historical participation, attribution, references, and workflow ownership do not preserve Activity visibility after revoke.
- My Work is factual creator/contributor/Draft discovery plus current authorization. It is not a task system, workload metric, or employee-productivity feature.
- Shared With Me includes only direct current Device assignments and direct current generic grants. Exclude requests, publication, Comments, mentions, Notifications, references, creator, and contributor relationships.
- Collaboration Overview is fixed, bounded, read-only composition. Preview and count eligibility must come from the same canonical provider and the endpoint must create no persistent Dashboard or other side effect.

## Validation and errors

Use explicit Form Requests or validated allow-lists and prohibit caller-supplied actor, tenant, role, and status authority. Use consistent 401/403/404/409/410/422/429/5xx semantics from `BACKEND.md`. Never disclose stack traces, SQL, filesystem paths, tenant existence, or secret values. Conflicts return the exact machine-readable DTO expected by the frontend.

## Writes, Safe Save, and idempotency

Classify mutations as revisionable configuration, live runtime, security/access, lifecycle, review/publication, or file operation. Only revisionable configuration uses Safe Save. Use transaction/lock/revalidation, deterministic safe snapshots, meaningful no-op detection, and one Revision. Scope idempotency to the actual actor/resource/command/base/fingerprint and materialize effects after commit.

## Lists and performance

Authorize before effective pagination. New conventional lists use `page` and bounded `per_page` (default 25, maximum 100 unless stricter). Use bounded previews, batch related lookups, avoid N+1 and request-per-row patterns, and never optimize by broadening data visibility. Caches are optional accelerators and never authority.

## Jobs and scheduler

Queue stable identifiers, not secret-rich resources or authorization decisions. Reload tenant, resource, lifecycle, generation, and eligibility at execution. Define retry, backoff, timeout, idempotency, failure recovery, and scheduler overlap behavior.

## Secrets, runtime, and files

Keep credentials/write-only secrets, telemetry, execution/delivery/run/deployment state, access state, lifecycle, and publication separate from generic revisions/drafts. Authorize exact parent/file downloads. Never log or cache plaintext secrets.

- Dashboard/resource access never grants access to referenced Device sources. Resolve every live source against current Device authority and return a safe unavailable presentation when source access is absent.
- Dashboard widget types and configuration keys must be server-allowlisted. Saved widget configuration uses Safe Save/Revisions; capability and availability fields are presentation only. Never substitute sample telemetry, invented map coordinates, arbitrary image URLs, or a cached widget DTO for current source authorization.
- Runtime projections that reference a private definition and a Device require both authorities. Automation execution history requires the direct Automation grant/Admin rule plus current Device source access.
- Upload storage keys and download paths are server-derived. Client filenames are presentation metadata only; sanitize them and never use them as paths.
- Jobs carry stable IDs and pinned provenance, recheck lifecycle/current access as applicable, and record safe terminal failure. Acceptance of a command, report, delivery, or deployment is not proof of external execution.

## Tests

Every contract slice needs success, validation, unauthenticated, same-Organization-but-unauthorized, cross-tenant, exact-child IDOR, revoke/downgrade, lifecycle, stale/race/idempotency, pagination, secret exclusion, and frontend integration coverage as applicable. Mocks do not replace a real frontend/API workflow.

## Documentation

Maintain only `BACKEND.md`, `BACKEND_STANDARDS.md`, and `BACKEND_VALIDATION.md`. Do not create per-feature, per-sprint, audit, matrix, security, or performance Markdown documents. The former requirements input has been incorporated into this canonical set.

## Final Admin scope

- Every `/admin/*` query and route-model binding must constrain the resource, target user, child, and option list to the authenticated Admin's current Organization before returning or mutating data.
- An accepted `organization_id` compatibility filter is never authority. Override or validate it against the current Organization; never use it to select another tenant.
- “Global Device”, “Global Operations”, inventory, and similar legacy names mean current-Organization-wide Admin views. They never mean platform-wide or cross-Organization authority.
- Admin detail, nested child, credential, file, monitoring, assignment, lifecycle, review, and runtime endpoints must return 404 for a foreign resource even when its ID is valid.

When the frontend contract changes, update `BACKEND.md` before or with implementation. If backend constraints require a coordinated contract change, update the frontend service/types and both canonical documentation sets together; the backend must never silently redefine the frontend contract.
