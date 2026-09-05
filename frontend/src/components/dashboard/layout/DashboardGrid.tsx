import { Responsive, WidthProvider } from "react-grid-layout/legacy";
import type { LayoutItem } from "react-grid-layout/legacy";
import type { DashboardWidgetDefinition, Widget } from "../../../types/dashboard";
import WidgetRenderer from "../../dashboard-builder/WidgetRenderer";
import WidgetErrorBoundary from "../widgets/WidgetErrorBoundary";
import "react-grid-layout/css/styles.css";
import "react-resizable/css/styles.css";

const Grid = WidthProvider(Responsive);

function removeOverlaps(items: LayoutItem[], columns: number) {
  const placed: LayoutItem[] = [];
  const overlaps = (a: LayoutItem, b: LayoutItem) =>
    a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;

  for (const source of [...items].sort((a, b) => a.y - b.y || a.x - b.x)) {
    const item = {
      ...source,
      w: Math.min(source.w, columns),
      x: Math.min(Math.max(0, source.x), Math.max(0, columns - Math.min(source.w, columns))),
      y: Math.max(0, source.y),
    };
    while (placed.some((other) => overlaps(item, other))) item.y += 1;
    placed.push(item);
  }

  return placed;
}

export default function DashboardGrid({ widgets, definitions = [], editable = false, onLayoutChange, onSelect, onComment, timeRange }: {
  widgets: Widget[];
  definitions?: DashboardWidgetDefinition[];
  editable?: boolean;
  onLayoutChange?: (id: string, layout: Widget["layout"]) => void;
  onSelect?: (id: string) => void;
  onComment?: (id: string) => void;
  timeRange?: "1h"|"6h"|"24h"|"7d"|"30d";
}) {
  const byType = new Map(definitions.map((definition) => [definition.type, definition]));
  const layout = widgets.map((widget) => {
    const definition = byType.get(widget.type);
    const minW = definition?.minWidth ?? 2;
    const minH = definition?.minHeight ?? 2;
    return { i: widget.id, ...widget.layout, w: Math.max(widget.layout.w, minW), h: Math.max(widget.layout.h, minH), minW, minH, maxW: definition?.maxWidth ?? 12, maxH: definition?.maxHeight ?? 12 };
  });
  const fitLayout = (columns: number) => removeOverlaps(layout.map((item) => ({ ...item, minW: Math.min(item.minW ?? 1, columns), maxW: Math.min(item.maxW ?? columns, columns), w: Math.min(item.w, columns), x: Math.min(item.x, Math.max(0, columns - Math.min(item.w, columns))) })),columns);
  const mobileLayout = layout.map((item, index) => ({ ...item, x: 0, y: layout.slice(0, index).reduce((height, previous) => height + previous.h, 0), w: 1, minW: 1, maxW: 1 }));
  const persist = (item: LayoutItem) => {
    if (editable) onLayoutChange?.(item.i, { x: item.x, y: item.y, w: item.w, h: item.h });
  };
  const persistLayout = (items: readonly LayoutItem[]) => {
    if (!editable) return;
    for (const item of items) persist(item);
  };

  return <Grid className="dashboard-grid" layouts={{ lg: removeOverlaps(layout,12), md: fitLayout(8), sm: fitLayout(4), xs: mobileLayout }} breakpoints={{ lg: 1200, md: 900, sm: 600, xs: 0 }} cols={{ lg: 12, md: 8, sm: 4, xs: 1 }} rowHeight={48} compactType="vertical" allowOverlap={false} preventCollision={false} isBounded isDraggable={editable} isResizable={editable} draggableCancel="button,input,select,textarea,a,[role='button'],.react-resizable-handle" resizeHandles={["se"]} onDragStop={(next) => persistLayout(next)} onResizeStop={(next) => persistLayout(next)}>
    {widgets.map((widget) => <div key={widget.id} onClick={() => onSelect?.(widget.id)} className="min-w-0"><WidgetErrorBoundary><WidgetRenderer widget={widget} editable={editable} onComment={onComment ? () => onComment(widget.id) : undefined} timeRange={timeRange} /></WidgetErrorBoundary></div>)}
  </Grid>;
}
