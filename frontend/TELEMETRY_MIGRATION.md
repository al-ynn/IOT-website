# Telemetry experience migration

The previous frontend contained several disconnected telemetry components and referenced `/telemetry/history` and `/telemetry/export`, neither of which exists in the backend. Device telemetry also owned a separate explorer implementation.

Phase 18 consolidates reads through the existing analytics endpoints:

- Device metric discovery: `GET /analytics/devices/{device}`
- Aggregated series: `GET /analytics/telemetry/{metric}`

`TelemetryPanel` now powers the main Telemetry Center, metric detail pages, and the Device Center telemetry tab. It supports backend ranges, custom dates, intervals, and aggregations without calculating substitute statistics in the browser.

The backend permission and entitlement contract for telemetry reads is currently `analytics.view` plus `analytics.advanced`. No `telemetry.view` permission is defined by the backend, so no new permission was invented.

The backend has no telemetry export endpoint. Export remains visibly unavailable and does not create a fake file.
