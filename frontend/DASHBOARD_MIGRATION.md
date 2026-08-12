# Dashboard experience migration

The previous dashboard overview used custom card styling, the dashboard list had no loading/error/empty states, and the detail route did not render widgets. The previous widget renderer also displayed hardcoded sample readings.

Phase 16 retains the dashboard API, device API, telemetry-history API, Zustand builder store, billing context, permission context, and `react-grid-layout`. Presentation now uses Phase 13 components and the Phase 15 shell.

Widget rendering is data-source driven. A configured widget requests its real device or telemetry source; an unconfigured widget shows an honest configuration state. No fallback telemetry values are generated.

Legacy files under `components/dashboard-builder/` remain where required by older imports, while the active builder foundation lives under `components/dashboard/builder/`. They can be removed in a later cleanup only after an import audit.
