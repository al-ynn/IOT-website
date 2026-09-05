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
