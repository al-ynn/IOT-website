import type { ReactNode } from "react";
import { AlertCircle, GripVertical, MessageSquare } from "lucide-react";
import { Card, LoadingState } from "../../ui";

/*
  Widget frame — premium technical card with subtle top luminous strip,
  compact header, and stable content well. Preserves editable drag surface
  and comment action. All colors flow from --ds-* tokens (light + dark).
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
    <Card
      variant="elevated"
      className="flex h-full min-h-32 flex-col overflow-hidden"
      aria-label={`${title} widget`}
    >
      <div aria-hidden className="tech-strip absolute inset-x-0 top-0 h-px opacity-70" />
      <div
        className={`flex h-10 shrink-0 items-center justify-between gap-2 border-b border-[var(--ds-border-subtle)] bg-[var(--ds-card-alt)] px-3 ${
          editable ? "dashboard-widget-drag-surface" : ""
        }`}
      >
        <h3
          className="min-w-0 flex-1 truncate text-[11.5px] font-semibold uppercase tracking-[.06em] text-[var(--ds-text-muted)]"
          title={title}
        >
          {title}
        </h3>
        <div className="flex shrink-0 items-center gap-1">
          {onComment && (
            <button
              type="button"
              aria-label={`Comment on ${title} widget`}
              onClick={(event) => {
                event.stopPropagation();
                onComment();
              }}
              className="grid h-6 w-6 place-items-center rounded text-[var(--ds-text-subtle)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)]"
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
    </Card>
  );
}
