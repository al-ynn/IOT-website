# Analytics experience migration

The previous Analytics page combined raw selects, custom cards, and a standalone chart in one component. It did not provide device or metric routes, statistics tables, explicit unsupported states, or reusable telemetry filters.

Phase 19 keeps the existing analytics API and consolidates interactive trend analysis through the Phase 18 `TelemetryPanel`. Organization KPIs and latest measurements come directly from `/analytics/summary`; per-device metrics come from `/analytics/devices/{device}`; trends and complete statistics come from `/analytics/telemetry/{metric}`.

The backend currently supports single-device metric series only. Cross-device comparison, insights, recommendations, and export are presented as unavailable rather than calculated or generated in the frontend.

All analytics routes retain the existing `analytics.view` permission and `analytics.advanced` entitlement gates.
