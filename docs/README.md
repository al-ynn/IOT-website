# IoT Platform

Current documentation:

- [Frontend architecture](docs/frontend/FRONTEND.md)
- [Frontend standards](docs/frontend/FRONTEND_STANDARDS.md)
- [Frontend validation](docs/frontend/FRONTEND_VALIDATION.md)
- [Backend contract and architecture](docs/backend/BACKEND.md)
- [Backend standards](docs/backend/BACKEND_STANDARDS.md)
- [Backend validation](docs/backend/BACKEND_VALIDATION.md)

Frontend changes update the three canonical frontend documents instead of creating feature-, phase-, audit-, or matrix-specific Markdown files.

The backend is developed from the verified frontend contract. Start with the frontend routes, pages, services, API-facing types, and workflow states, then use the backend guide to implement server-authoritative security and behavior. Existing backend code is reference material, not the requirements source.

## Local sample system

Run `php artisan db:seed --class=DevelopmentUserSeeder` from `backend` to create or reset the single local sample system. The seeder runs only in `local` and `testing`; it does not populate production.

The result is one clearly labelled **Sample IoT Demo** Organization containing one **Sample Environmental Controller** Template, one **Sample Device — Environmental Controller**, one **Sample Facility**, and one **Sample Device Dashboard**. The Dashboard contains every widget registered for the personal context exactly once. Its telemetry and operational-event history are deterministic child records belonging to that one Device. Control widgets remain visibly read-only because no writable Device command transport exists.

Use the existing deterministic local Staff or Admin persona, then open Templates, Devices, or Dashboards and select the resource whose name starts with “Sample”. Credentials remain confined to development fixture conventions and are not repeated in production documentation.
