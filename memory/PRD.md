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

## Second Deep Redesign Pass (2026-02, after rejection of pass 1)
User rejected pass 1 as "token swap / too close to original". Reference zips re-studied: refs 8/9/10 (futuristic glass command-center) define identity, ref 7 defines palette.

### Rebuilt in pass 2:
- theme.css v2: 5-level surface hierarchy, luminous border tokens, ambient glow tokens, chart series tokens
- globals.css v2: layered backdrop (radial ambient glows + 64px grid + 22px dot matrix via ::before/::after, no layout side effects), utilities: luminous-top, tech-corners, stroke-gradient, pulse-dot, surface-glass/raised/inset, full MapLibre theme integration (controls, popups, attribution)
- PrimarySidebar: gradient brand chip with glow, section labels with gradient hairlines, active = luminous blue pill + left beam + glow dot, "Systems nominal" status footer with pulse
- SecondarySidebar: ambient top glow, luminous "Current area" header, beam-style selected states
- AppHeader: glass bar (backdrop-blur-xl) + tech-strip luminous bottom edge, bordered icon buttons
- UserMenu: gradient avatar, elevated dropdown with luminous top, role chip
- SearchCommand: luminous command dialog with gradient submit
- NotificationBell: gradient unread badge with glow, unread rows get luminous top + glow dot + NEW chip
- DashboardWidget frame v2: tech-corners + luminous-top + gradient header band + glowing type dot + hover glow lift
- MetricWidget v2: gradient-text KPI value, icon trend chip, gradient accent underline
- GaugeWidget v2: 24-tick instrument bezel, gradient conic arc with glow, recessed dial
- StatusWidget v2: recessed beacon panel with glowing orb + pulse for online
- DeviceWidget v2: gradient icon chip, status pill, inset telemetry panel with gradient battery bar
- ChartFrame v2: SVG gradient defs (area fill, line stroke, bar fill), feGaussianBlur line glow, endpoint marker halo, dashed grid, gradient rounded bars
- components/auth/AuthLayout v2: full command-center entrance (grid+dots+glow backdrop, glass panel, corner ticks, gradient logo, "Secure access" footer)
- SecondarySidebar typed with ResolvedSecondaryNavigation (removed any)

### Verified:
- yarn lint: clean
- tsc: only 3 pre-existing errors in admin pages (untouched)
- yarn build: succeeds (15s, rolldown)
- Screenshots: login (dark, glass+grid), dashboard dark (luminous widgets), devices light (blue-tinted light theme), mobile 390px zero overflow

## Fourth Pass — Neon Command-Center Widgets (2026-02)
User demanded reference-level drama: full luminous borders, glowing icon chips, huge glowing numbers.
- DashboardWidget v3: full luminous border ring + outer glow bloom + inner ambient wash + top light beam + corner ticks; glowing uppercase header dot
- MetricWidget v3: glowing icon chip → eyebrow → 32px number with text-shadow bloom → glowing trend chip
- GaugeWidget v3: neon bezel ring, drop-shadow arc, recessed dial, glowing center value
- StatusWidget v3: glowing beacon orb in luminous ring + glowing uppercase state label
- WidgetRenderer: device_count + event_count_tile now glowing icon-chip stat blocks (inline SVG chips, no new deps); global summary tiles get glowing numbers
- Verified: lint clean, tsc clean (except 3 pre-existing admin files), build passes, dark/light/mobile screenshots confirmed, zero overflow
