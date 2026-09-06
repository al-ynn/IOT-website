import { useState } from "react";
import { Responsive, WidthProvider } from "react-grid-layout/legacy";
import type { LayoutItem } from "react-grid-layout/legacy";
import type { DashboardWidgetDefinition, Widget } from "../../../types/dashboard";
import WidgetRenderer from "../../dashboard-builder/WidgetRenderer";
import WidgetErrorBoundary from "../widgets/WidgetErrorBoundary";
import "react-grid-layout/css/styles.css";
import "react-resizable/css/styles.css";

const Grid = WidthProvider(Responsive);

function removeOverlaps(items: LayoutItem[], columns: number, pinnedId?: string) {
  const placed: LayoutItem[] = [];
  const overlaps = (a: LayoutItem, b: LayoutItem) =>
    a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;

  for (const source of [...items].sort((a, b) => {
    if (a.i === pinnedId) return -1;
    if (b.i === pinnedId) return 1;
    return a.y - b.y || a.x - b.x;
  })) {
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

function hasCollision(items: readonly LayoutItem[], moving: LayoutItem) {
  return items.some((item) => item.i !== moving.i && moving.x < item.x + item.w && moving.x + moving.w > item.x && moving.y < item.y + item.h && moving.y + moving.h > item.y);
}

function widgetMinimum(widget: Widget, definition?: DashboardWidgetDefinition) {
  if (widget.type !== "label") return { minW: definition?.minWidth ?? 2, minH: definition?.minHeight ?? 2 };

  const fontSize = Math.max(12, Math.min(64, widget.settings.fontSize ?? 24));
  return {
    minW: Math.max(1, Math.min(4, Math.ceil(fontSize / 16))),
    minH: Math.max(2, Math.min(3, 1 + Math.ceil(fontSize / 32))),
  };
}

export default function DashboardGrid({ widgets, definitions = [], editable = false, onLayoutsChange, onSelect, onComment, timeRange }: {
  widgets: Widget[];
  definitions?: DashboardWidgetDefinition[];
  editable?: boolean;
  onLayoutsChange?: (layouts: Array<{id:string;layout:Widget["layout"]}>) => void;
  onSelect?: (id: string) => void;
  onComment?: (id: string) => void;
  timeRange?: "1h"|"6h"|"24h"|"7d"|"30d";
}) {
  const [activeColumns, setActiveColumns] = useState(12);
  const [placementInvalid, setPlacementInvalid] = useState(false);
  const [interaction, setInteraction] = useState<"idle"|"drag"|"resize">("idle");
  const byType = new Map(definitions.map((definition) => [definition.type, definition]));
  const layout = widgets.map((widget) => {
    const definition = byType.get(widget.type);
    const { minW, minH } = widgetMinimum(widget, definition);
    return { i: widget.id, ...widget.layout, w: Math.max(widget.layout.w, minW), h: Math.max(widget.layout.h, minH), minW, minH, maxW: definition?.maxWidth ?? 12, maxH: definition?.maxHeight ?? 12 };
  });
  const fitLayout = (columns: number) => removeOverlaps(layout.map((item) => {
    const minW = Math.max(1, Math.min(columns, Math.ceil((item.minW ?? 1) * columns / 12)));
    const maxW = Math.max(minW, Math.min(columns, Math.ceil((item.maxW ?? 12) * columns / 12)));
    const width = Math.max(minW, Math.min(maxW, Math.round(item.w * columns / 12)));
    const x = Math.min(Math.max(0, Math.round(item.x * columns / 12)), columns - width);
    return { ...item, x, w: width, minW, maxW };
  }), columns);
  const mobileLayout = removeOverlaps(layout.map((item) => ({ ...item, x: 0, w: 1, minW: 1, maxW: 1 })), 1);
  const persistLayout = (items: readonly LayoutItem[], pinnedId?: string) => {
    if (!editable) return;
    const collisionFree = removeOverlaps(items.map((item) => ({ ...item })), activeColumns, pinnedId);
    const scale = 12 / activeColumns;
    onLayoutsChange?.(collisionFree.map(item=>({id:item.i,layout:{x:Math.round(item.x*scale),y:item.y,w:Math.max(1,Math.round(item.w*scale)),h:item.h}})));
  };

  return <Grid className={`dashboard-grid ${interaction!=="idle"?"grid-interacting":""} ${placementInvalid?"placement-invalid":"placement-valid"}`} layouts={{ lg: removeOverlaps(layout,12), md: fitLayout(8), sm: fitLayout(4), xs: mobileLayout }} breakpoints={{ lg: 1200, md: 900, sm: 600, xs: 0 }} cols={{ lg: 12, md: 8, sm: 4, xs: 1 }} rowHeight={48} margin={[12,12]} containerPadding={[2,2]} compactType={null} allowOverlap={false} preventCollision={false} isBounded isDraggable={editable} isResizable={editable} draggableCancel="button,input,select,textarea,a,[role='button'],.react-resizable-handle" resizeHandles={["se"]} onBreakpointChange={(_breakpoint, columns)=>setActiveColumns(columns)} onDragStart={()=>{setInteraction("drag");setPlacementInvalid(false)}} onDrag={(next,_old,item)=>setPlacementInvalid(item?hasCollision(next,item):false)} onDragStop={(next,_old,item)=>{setInteraction("idle");setPlacementInvalid(false);persistLayout(next,item?.i)}} onResizeStart={()=>{setInteraction("resize");setPlacementInvalid(false)}} onResize={(next,_old,item)=>{const colliding=item?hasCollision(next,item):false;setPlacementInvalid(colliding);if(colliding)persistLayout(next,item?.i)}} onResizeStop={(next,_old,item)=>{setInteraction("idle");setPlacementInvalid(false);persistLayout(next,item?.i)}}>
    {widgets.map((widget) => <div key={widget.id} onClick={() => onSelect?.(widget.id)} className="min-w-0"><WidgetErrorBoundary><WidgetRenderer widget={widget} editable={editable} onComment={onComment ? () => onComment(widget.id) : undefined} timeRange={timeRange} /></WidgetErrorBoundary></div>)}
  </Grid>;
}
