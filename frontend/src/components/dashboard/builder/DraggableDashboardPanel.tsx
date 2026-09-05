import { useCallback, useRef, useState, type MouseEvent, type PointerEvent, type ReactNode } from "react";

interface Props {
  side: "left" | "right";
  label: string;
  expanded: boolean;
  onActivate?: () => void;
  children: ReactNode;
}

export default function DraggableDashboardPanel({ side, label, expanded, onActivate, children }: Props) {
  const panelRef = useRef<HTMLElement>(null);
  const dragRef = useRef<{ pointerX: number; pointerY: number; left: number; top: number; moved: boolean } | null>(null);
  const suppressClickRef = useRef(false);
  const [position, setPosition] = useState<{ left?: number; top: number; right?: number }>(() =>
    side === "left" ? { left: 0, top: 12 } : { right: 0, top: 12 },
  );

  const startDrag = useCallback((event: PointerEvent<HTMLElement>) => {
    const interactiveTarget = (event.target as HTMLElement).closest("button, input, select, textarea, a");
    if (event.button !== 0 || (expanded && interactiveTarget)) return;
    const panel = panelRef.current;
    const bounds = panel?.parentElement?.getBoundingClientRect();
    const rect = panel?.getBoundingClientRect();
    if (!panel || !bounds || !rect) return;
    dragRef.current = { pointerX: event.clientX, pointerY: event.clientY, left: rect.left - bounds.left, top: rect.top - bounds.top, moved: false };
    event.currentTarget.setPointerCapture(event.pointerId);
    if (!interactiveTarget) event.preventDefault();
  }, [expanded]);

  const dragPanel = useCallback((event: PointerEvent<HTMLElement>) => {
    const origin = dragRef.current;
    const panel = panelRef.current;
    const bounds = panel?.parentElement?.getBoundingClientRect();
    if (!origin || !panel || !bounds) return;
    const nextLeft = Math.min(Math.max(0, origin.left + event.clientX - origin.pointerX), Math.max(0, bounds.width - panel.offsetWidth));
    const nextTop = Math.min(Math.max(0, origin.top + event.clientY - origin.pointerY), Math.max(0, bounds.height - panel.offsetHeight));
    if (Math.abs(event.clientX-origin.pointerX)>4||Math.abs(event.clientY-origin.pointerY)>4) origin.moved=true;
    setPosition({ left: nextLeft, top: nextTop });
  }, []);

  const stopDrag = useCallback(() => {
    const drag = dragRef.current;
    const panel = panelRef.current;
    const bounds = panel?.parentElement?.getBoundingClientRect();
    const rect = panel?.getBoundingClientRect();
    suppressClickRef.current = !expanded && Boolean(drag);
    if (drag?.moved && panel && bounds && rect) {
      const dockLeft = rect.left + rect.width / 2 < bounds.left + bounds.width / 2;
      const top = Math.min(Math.max(0, rect.top - bounds.top), Math.max(0, bounds.height - panel.offsetHeight));
      setPosition(dockLeft ? { left: 0, top } : { right: 0, top });
    }
    if (drag && !drag.moved && !expanded) onActivate?.();
    dragRef.current = null;
  }, [expanded, onActivate]);
  const suppressDraggedClick=useCallback((event:MouseEvent<HTMLElement>)=>{if(!suppressClickRef.current)return;suppressClickRef.current=false;event.preventDefault();event.stopPropagation()},[]);

  return (
    <aside
      ref={panelRef}
      data-dashboard-floating-panel={side}
      className={`pointer-events-auto absolute z-40 max-w-full ${expanded ? "w-[min(280px,calc(100vw-2rem))]" : "w-auto"}`}
      style={position}
      aria-label={label}
      onPointerDown={startDrag}
      onPointerMove={dragPanel}
      onPointerUp={stopDrag}
      onPointerCancel={stopDrag}
      onClickCapture={suppressDraggedClick}
    >
      <div className="relative cursor-grab touch-none select-none rounded-lg bg-[var(--ds-surface-raised)] active:cursor-grabbing" title={`Hold and drag ${label}`}>
       {children}
      </div>
    </aside>
  );
}
