import type { ReactNode } from "react";
import { AlertCircle, GripVertical, MessageSquare } from "lucide-react";
import { LoadingState } from "../../ui";

/*
  Widget frame v2 — premium command-center tile (refs 8/9/10):
  layered glass surface, luminous top edge, technical corner ticks,
  compact header with glowing type dot, depth shadow.
  Editable drag surface + comment action preserved.
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
      className="group/widget tech-corners luminous-top relative flex h-full min-h-32 flex-col overflow-hidden rounded-[12px] border border-[var(--ds-border-subtle)] transition-[box-shadow,border-color] hover:border-[var(--ds-primary-outline)] hover:shadow-[var(--ds-glow-soft)]"
      style={{
        background:
          "linear-gradient(180deg, color-mix(in oklab, var(--ds-card-alt) 55%, var(--ds-card)), var(--ds-card))",
        boxShadow: "var(--ds-shadow-md)",
      }}
    >
      <div
        className={`relative flex h-10 shrink-0 items-center justify-between gap-2 border-b border-[var(--ds-border-subtle)] px-3 ${
          editable ? "dashboard-widget-drag-surface" : ""
        }`}
        style={{
          background:
            "linear-gradient(180deg, color-mix(in oklab, var(--ds-surface-elevated) 60%, transparent), transparent)",
        }}
      >
        <div className="flex min-w-0 flex-1 items-center gap-2">
          <span
            aria-hidden
            className="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--ds-primary)]"
            style={{ boxShadow: "0 0 6px 1px var(--ds-primary-glow)" }}
          />
          <h3
            className="truncate text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[var(--ds-text-muted)]"
            title={title}
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
