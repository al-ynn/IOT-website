# IoT Platform — UI/UX Visual Redesign

## Original ask
System-wide visual redesign of an existing, fully functional Laravel + React/Vite IoT platform.
Preserve ALL functionality, APIs, DB, auth, routes, customization, widgets. Change only the visual layer.

## Stack found
- Backend: Laravel (PHP) at /app/backend
- Frontend: React 19 + Vite + TypeScript + Tailwind v4 at /app/frontend
- Libs: react-grid-layout, maplibre-gl, framer-motion, zustand, react-query, react-router 7, playwright (E2E)

## User-approved design direction
- Palette (strict): #3B82F6 primary, #93C5FD soft blue, #DBEAFE very light blue, #1F2937 slate, #6B7280 mid gray, #F3F4F6/#E5E7EB light neutrals, #FFFFFF, #FACC15 accent, #FDE68A soft accent
- Font: Geist (Google Fonts) with sensible system fallback
- Character: premium IoT command-center — clean composition, vibrant data, realistic depth, selective 3D, futuristic technical accents, restrained glow
- Density: compact professional (no chunky UI, no dead space)
- Both light + dark modes, fully responsive 320–1920px+
- Preserve --ds-* token API used across app

## Redesign implemented (2026-02)
- design-system/themes/theme.css → full new token set (light + dark) with palette, surfaces, shadows, glows, grid backdrop
- styles/globals.css → Geist font, app-backdrop technical grid, surface-glass/raised/inset utilities, refined scrollbar/selection/focus, tech-strip accent
- index.html → Geist + Geist Mono via Google Fonts
- UI primitives rewritten: Button (subtle 3D + warning variant), Card (6 variants: default/elevated/interactive/glass/inset/accent + Footer), FormControls (refined inputs, Select, Textarea, tactile Switch, Slider), Status (7 tones), DataTable (sticky header, primary hover), Feedback (loading/skeleton/empty/error with shimmer), Toast, Overlays (Modal + Drawer w/ tech-strip + backdrop-blur), Typography (Metric with tabular-nums)
- Widget frames: DashboardWidget (tech-strip + compact header), MetricWidget (KPI hierarchy + tone trend chip), GaugeWidget (premium ring + glow), StatusWidget (dot + badge), DeviceWidget (compact)
- Layouts: AppLayout wrapped in `.app-backdrop`; AuthLayout fully redesigned with palette + tech accent
- All existing pages / sidebar / header inherit via --ds-* token cascade (no page code touched)

## Preserved (non-negotiable)
- All routes, resource models, authorization guards, widget config, API contracts, DB schema, backend logic, dashboard customization

## Prioritized backlog
- P1: Visual polish pass on PrimarySidebar / AppHeader (dedicated premium treatment beyond token cascade)
- P1: Chart widgets (Area/Line/Bar) color palette alignment (only if hardcoded colors found)
- P1: Fine-tune per reference images: dotted-matrix backdrop option, luminous corner details on selected widgets
- P2: Notification center visual refresh
- P2: Map widget frame/legend/toolbar polish
- P2: Admin review/publication screens polish

## Environment / Preview Fixes (2026-02, after pod extraction)
- Frontend `package.json`: added `"start": "vite --host 0.0.0.0 --port 3000"` so the readonly supervisor config (`yarn start`) works with Vite
- `vite.config.ts`: `server.allowedHosts: true` (preview domains) + proxy default target corrected to `http://127.0.0.1:8001` (Laravel) — was 8000
- `frontend/.env` created: `VITE_API_URL=/api`, `VITE_API_PROXY_TARGET=http://127.0.0.1:8001`
- Reinstalled node_modules on Linux (`yarn install --ignore-engines`; zip contained Windows native binaries that broke rolldown)
- Installed PHP 8.4 (SURY repo) — vendor/composer requires >= 8.4.1; Laravel runs via `php8.4 artisan serve --host=0.0.0.0 --port=8001`
- Bootstrap script for pod restarts: /app/scripts/start-stack.sh
- Login verified end-to-end: admin@iot-platform.test / Admin123! → token → /app/dashboard renders with all widgets (switch, slider, label, device count, device table, geomap, image map, metrics charts) in BOTH dark + light themes
- Bug fixed: `.app-backdrop > * { position: relative }` was overriding fixed sidebar positioning and pushing content below the fold — removed; desktop/mobile verified, zero horizontal overflow
- Known platform-level (not app) note: the `*.cluster-7.preview.emergentcf.cloud` preview domain returns 403 from Google LB — the primary `*.preview.emergentagent.com` preview URL works correctly
