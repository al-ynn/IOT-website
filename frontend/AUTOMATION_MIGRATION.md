# Automation experience migration

The previous automation UI used legacy cards, a single-screen builder with hardcoded defaults, incomplete page states, and separate execution cards. Phase 20 retains the Laravel automation engine, services, types, permissions, billing limits, and template endpoints while replacing presentation with Phase 13 components and the Phase 15 shell.

The six-step builder submits only backend-supported definitions. It never evaluates conditions or executes actions in the browser. Supported triggers are telemetry, device status, schedule, and manual; notification is the only supported action. Schedule and condition controls follow the Laravel validator.

Basic automation remains available through `automation.basic`. Conditions and schedules are presented only when `automation.advanced` is available, and Laravel remains the final entitlement and validation authority.

Execution history and detail render only persisted API logs. Templates are loaded and instantiated through backend template endpoints; no frontend templates are included.
