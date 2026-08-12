# IoT Platform Design System

The design system is the source of truth for application UI. It targets compact, information-dense industrial SaaS interfaces and supports dark and future light themes.

## Structure

- `tokens/`: typed color, typography, spacing, radius, shadow, motion, and icon values.
- `themes/`: CSS custom properties plus `ThemeProvider` and `useTheme`.
- `components/`: stable re-exports of primitives implemented in `src/components/ui`.
- `index.ts`: public design-system API.

Import primitives from `design-system` in new domain components:

```tsx
import { Badge, Button, Card, CardContent, Input } from "../design-system";
```

## Visual rules

- Application page titles should normally be 24–32px; section headings 14–18px.
- Body UI is 13–14px. Captions and labels are 11–12px.
- Use the 4/8/12/16/20/24/32px spacing rhythm.
- Default controls are 36px high; compact controls are 28–32px.
- Cards use 10px radii and 12–16px internal spacing. Avoid oversized empty cards.
- Prefer borders and surface contrast over heavy shadows.
- Use primary blue for actions and cyan sparingly for telemetry emphasis.
- Success, warning, and danger colors communicate state, not decoration.

## Components

- `Button`: primary, secondary, outline, ghost, danger; compact, small, default.
- `Card`: default, elevated, interactive with composable header/content.
- `Input`, `Select`, `Textarea`: label, helper, error, disabled, ARIA wiring.
- `DataTable`: typed dense data presentation with captions and empty state.
- `Badge`, `Chip`, `StatusIndicator`: consistent system and device states.
- `LoadingState`, `Skeleton`, `EmptyState`, `ErrorState`: predictable feedback.
- `Toast`: accessible transient message presentation.
- `Modal`, `Drawer`: portal overlays, Escape handling, scroll locking, semantic dialogs.
- `Icon`: centralized Lucide sizing and accessibility behavior.
- `PageTitle`, `SectionTitle`, `BodyText`, `Caption`, `Label`: controlled application typography.

## Accessibility

Use real buttons and links, visible focus states, explicit labels, table captions, semantic status roles, and descriptive empty/error text. Motion automatically collapses for `prefers-reduced-motion`.

## Themes

`ThemeProvider` defaults to dark and persists the user choice under `iot_ui_theme`. Components consume semantic CSS variables, so light theme support does not require component duplication.
