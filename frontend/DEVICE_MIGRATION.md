# Device management migration

The previous device module used a card-only inventory, a single-page registration form, legacy fixed-color components, and a Device Center with only Overview and Analytics links. Loading, empty, error, filtering, responsive-table, billing-limit, and most device-center states were incomplete.

Phase 17 retains the canonical device, analytics, billing, authentication, and permission services. The backend currently supports device listing, creation, viewing, and deletion. It does not expose device updates, commands, activity history, lifecycle history, or credential/security endpoints, so those panels clearly report their unavailable state.

The backend permission contract uses `device.view` and `device.manage`. The latter governs supported create and delete operations; no parallel permission system was introduced.

Device telemetry and statistics use the existing analytics endpoints. No device status or telemetry values are generated in the frontend.
