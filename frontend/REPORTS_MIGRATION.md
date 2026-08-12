# Reports and exports migration

Phase 26 replaces legacy report and export screens that called nonexistent APIs or displayed hardcoded builder data.

## Backend audit

Laravel has no report controller, export controller, report/export model, storage-download route, generation job, report permission, or report billing entitlement. `routes/api.php` contains no report, export, or download endpoints.

The legacy frontend referenced unsupported routes including `/reports`, `/reports/{id}/generate`, `/reports/export`, `/reports/exports`, and `/reports/schedules`. It also included browser-side export naming, scheduling decisions, and hardcoded devices and metrics. These unsupported integrations and mock builder inputs were removed.

## Routes

- `/app/reports`
- `/app/reports/create`
- `/app/reports/:id`
- `/app/exports`

Every route is authenticated through the existing customer shell. Because Laravel defines neither report permissions nor a report entitlement, no new permission or billing key was invented. Each page presents an accessible unavailable state and makes no report/export network request.

Downloads will only be enabled in the future from backend-provided file URLs. The frontend does not generate CSV, PDF, Excel, Blob, or object URLs.
