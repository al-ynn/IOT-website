# Application shell migration

## Previous shell

`CustomerLayout` and `AdminLayout` each owned separate navigation arrays, sidebars, headers, user displays, and logout controls. Styling was fixed to dark colors, customer mobile behavior was embedded in the layout, and the admin shell had no mobile navigation.

## Current shell

Both layouts compose the shared components in `src/components/layout/`:

- `AppSidebar` renders centralized navigation and supports active, hover, permission-hidden, entitlement-locked, expanded, and collapsed states.
- `MobileSidebar` supplies the accessible drawer and Escape-key behavior.
- `AppHeader` composes route-derived breadcrumbs, global-search foundation, notifications, theme control, and the user menu.
- `UserMenu` uses the existing `AuthContext` logout flow.

Customer and admin route definitions retain the existing `ProtectedRoute` wrappers. Feature pages were not rewritten.

## Navigation sources

- Customer: `src/navigation/customer-navigation.ts`
- Administration: `src/navigation/admin-navigation.ts`
- Shared item contract: `src/navigation/app-navigation.ts`

Telemetry, workspace settings, and system management currently use the existing deferred-feature presentation until their product phases provide real pages.
