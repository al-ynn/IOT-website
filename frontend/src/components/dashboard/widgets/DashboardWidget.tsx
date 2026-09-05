import type { ReactNode } from "react";
import { AlertCircle, GripVertical, MessageSquare } from "lucide-react";
import { LoadingState } from "../../ui";

/*
  Widget frame v3 — full neon command-center tile:
  luminous border ring all around, outer glow bloom, inner ambient wash,
  glowing header dot, hover intensifies the ring.
*/
export default function DashboardWidget({
  title,
  children,
  loading = false,
  error,
  empty = false,
  emptyText = "No data available.",
  editable = false,
  onComment,
}: {
  title: string;
  children?: ReactNode;
  loading?: boolean;
  error?: string;
  empty?: boolean;
  emptyText?: string;
  editable?: boolean;
  onComment?: () => void;
}) {
  return (
    <section
      aria-label={`${title} widget`}
      className="group/widget relative flex h-full min-h-32 flex-col overflow-hidden rounded-[14px] transition-[box-shadow,border-color] duration-200"
      style={{
        background:
          "linear-gradient(165deg, color-mix(in oklab, var(--ds-card-alt) 60%, var(--ds-card)), var(--ds-card) 55%)",
        border: "1px solid var(--ds-border-luminous)",
        boxShadow:
          "0 0 0 1px color-mix(in oklab, var(--ds-primary) 12%, transparent), " +
          "0 0 22px -6px var(--ds-primary-glow), " +
          "inset 0 0 32px -18px var(--ds-primary-glow), " +
          "var(--ds-shadow-md)",
      }}
    >
      {/* luminous top beam */}
      <span
        aria-hidden
        className="pointer-events-none absolute inset-x-6 top-0 h-[2px] rounded-full"
        style={{
          background: "linear-gradient(90deg, transparent, var(--ds-primary), transparent)",
          boxShadow: "0 0 10px 1px var(--ds-primary-glow)",
        }}
      />
      {/* corner ticks */}
      <span aria-hidden className="pointer-events-none absolute left-1.5 top-1.5 h-2.5 w-2.5 border-l border-t border-[var(--ds-primary)] opacity-70" />
      <span aria-hidden className="pointer-events-none absolute bottom-1.5 right-1.5 h-2.5 w-2.5 border-b border-r border-[var(--ds-primary)] opacity-70" />

      <div
        className={`relative flex h-10 shrink-0 items-center justify-between gap-2 border-b border-[var(--ds-border-subtle)] px-3 ${
          editable ? "dashboard-widget-drag-surface" : ""
        }`}
        style={{
          background:
            "linear-gradient(180deg, color-mix(in oklab, var(--ds-primary) 10%, transparent), transparent)",
        }}
      >
        <div className="flex min-w-0 flex-1 items-center gap-2">
          <span
            aria-hidden
            className="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--ds-primary)]"
            style={{ boxShadow: "0 0 8px 2px var(--ds-primary-glow)" }}
          />
          <h3
            className="truncate text-[10.5px] font-bold uppercase tracking-[0.18em] text-[var(--ds-primary-soft)]"
            title={title}
            style={{ textShadow: "0 0 12px var(--ds-primary-glow)" }}
          >
            {title}
          </h3>
        </div>
        <div className="flex shrink-0 items-center gap-1">
          {onComment && (
            <button
              type="button"
              aria-label={`Comment on ${title} widget`}
              onClick={(event) => {
                event.stopPropagation();
                onComment();
              }}
              className="grid h-6 w-6 place-items-center rounded-[6px] text-[var(--ds-text-subtle)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)]"
            >
              <MessageSquare size={13} />
            </button>
          )}
          {editable && (
            <GripVertical
              size={14}
              className="pointer-events-none text-[var(--ds-text-subtle)]"
              aria-label="Drag widget"
            />
          )}
        </div>
      </div>
      <div className="min-h-0 flex-1 p-3">
        {loading ? (
          <LoadingState label="Loading widget..." className="h-full min-h-20" />
        ) : error ? (
          <div
            role="alert"
            className="flex h-full min-h-20 items-center justify-center gap-2 text-center text-xs text-[var(--ds-danger)]"
          >
            <AlertCircle size={15} /> {error}
          </div>
        ) : empty ? (
          <div className="flex h-full min-h-20 items-center justify-center text-center text-xs text-[var(--ds-text-subtle)]">
            {emptyText}
          </div>
        ) : (
          children
        )}
      </div>
    </section>
  );
}
