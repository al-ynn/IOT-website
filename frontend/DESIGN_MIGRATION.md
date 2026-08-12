# Design-system migration

## Current system → replacement

| Existing pattern | Foundation replacement |
|---|---|
| Domain-specific raw buttons | `Button` variants and sizes |
| `AuthInput` and raw form controls | `Input`, `Select`, `Textarea` |
| Dashboard/analytics/admin metric cards | `Card`, `CardHeader`, `CardContent` |
| Device/lifecycle/subscription status components | `Badge`, `Chip`, `StatusIndicator` |
| Admin billing and audit tables | typed `DataTable` |
| Page-specific loading text | `LoadingState` or `Skeleton` |
| Page-specific empty/error blocks | `EmptyState`, `ErrorState` |
| Ad-hoc confirmation overlays | `Modal` |
| Mobile configuration panels | `Drawer` |
| Ad-hoc notification boxes | `Toast` |
| Hardcoded dark colors | semantic theme variables |

## Migration order

1. Keep `ThemeProvider`, global focus behavior, and existing application shells stable.
2. Migrate shared authentication and billing controls.
3. Migrate device lists/details and their statuses.
4. Migrate dashboards and Analytics cards/charts.
5. Migrate Automation forms, tables, execution feedback, and dialogs.
6. Migrate organization/profile pages.
7. Migrate platform-admin tables and cards.
8. Remove old components only after all imports have moved and behavior is verified.

Working pages must be migrated incrementally. Do not mass-replace domain components or change API/state behavior during visual migration.
