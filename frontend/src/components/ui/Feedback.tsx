import type { ReactNode, HTMLAttributes } from "react";
import { AlertTriangle, Inbox, LoaderCircle, RefreshCw } from "lucide-react";
import { cn } from "../../utils/cn";
import { Button } from "./Button";

export function LoadingState({
  label = "Loading…",
  className,
}: {
  label?: string;
  className?: string;
}) {
  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        "flex min-h-24 items-center justify-center gap-2 text-sm text-[var(--ds-text-muted)]",
        className,
      )}
    >
      <LoaderCircle aria-hidden className="animate-spin text-[var(--ds-primary)]" size={17} />
      <span>{label}</span>
    </div>
  );
}

export function Skeleton({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      aria-hidden
      className={cn(
        "relative overflow-hidden rounded-[6px] bg-[color-mix(in_oklab,var(--ds-text-muted)_14%,transparent)]",
        "before:absolute before:inset-0 before:animate-[shimmer_1.4s_ease-in-out_infinite] before:bg-[linear-gradient(90deg,transparent,color-mix(in_oklab,var(--ds-text)_10%,transparent),transparent)]",
        className,
      )}
      style={{ maskImage: "linear-gradient(90deg,#000,#000)" }}
      {...props}
    />
  );
}

export function EmptyState({
  title = "Nothing here yet",
  description,
  action,
  icon,
  className,
  headingLevel = 3,
}: {
  title?: string;
  description?: string;
  action?: ReactNode;
  icon?: ReactNode;
  className?: string;
  headingLevel?: 2 | 3;
}) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  return (
    <div
      className={cn(
        "flex min-h-40 flex-col items-center justify-center px-5 py-6 text-center",
        className,
      )}
    >
      <span className="grid h-11 w-11 place-items-center rounded-[10px] border border-[var(--ds-border-subtle)] bg-[var(--ds-primary-surface)] text-[var(--ds-primary)] shadow-[var(--ds-shadow-sm)]">
        {icon ?? <Inbox size={18} />}
      </span>
      <Heading className="mt-3 text-sm font-semibold text-[var(--ds-text)]">{title}</Heading>
      {description && (
        <p className="mt-1 max-w-sm text-xs leading-5 text-[var(--ds-text-muted)]">
          {description}
        </p>
      )}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

export function ErrorState({
  title = "Unable to load data",
  description = "Try again or contact support if the problem continues.",
  retry,
  className,
}: {
  title?: string;
  description?: string;
  retry?: () => void;
  className?: string;
}) {
  return (
    <div
      role="alert"
      className={cn(
        "flex min-h-40 flex-col items-center justify-center px-5 py-6 text-center",
        className,
      )}
    >
      <span className="grid h-11 w-11 place-items-center rounded-[10px] border border-[var(--ds-border-subtle)] bg-[var(--ds-danger-surface)] text-[var(--ds-danger)]">
        <AlertTriangle size={18} />
      </span>
      <h3 className="mt-3 text-sm font-semibold text-[var(--ds-text)]">{title}</h3>
      <p className="mt-1 max-w-sm text-xs leading-5 text-[var(--ds-text-muted)]">{description}</p>
      {retry && (
        <Button
          className="mt-4"
          variant="outline"
          size="small"
          leadingIcon={<RefreshCw size={14} />}
          onClick={retry}
        >
          Retry
        </Button>
      )}
    </div>
  );
}
