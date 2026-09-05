import type { HTMLAttributes } from "react";
import { cn } from "../../utils/cn";

export type StatusTone = "neutral" | "info" | "primary" | "accent" | "success" | "warning" | "danger";

/*
  Tones map to design tokens so both light + dark modes are correct automatically.
  A tiny inner highlight adds refined depth.
*/
const tones: Record<StatusTone, string> = {
  neutral:
    "bg-[color-mix(in_oklab,var(--ds-text-muted)_14%,transparent)] text-[var(--ds-text-muted)] ring-[color-mix(in_oklab,var(--ds-text-muted)_28%,transparent)]",
  info:
    "bg-[var(--ds-info-surface)] text-[var(--ds-info)] ring-[color-mix(in_oklab,var(--ds-info)_35%,transparent)]",
  primary:
    "bg-[var(--ds-primary-surface)] text-[var(--ds-primary)] ring-[var(--ds-primary-outline)]",
  accent:
    "bg-[var(--ds-accent-surface)] text-[var(--ds-accent)] ring-[var(--ds-accent-outline)]",
  success:
    "bg-[var(--ds-success-surface)] text-[var(--ds-success)] ring-[color-mix(in_oklab,var(--ds-success)_35%,transparent)]",
  warning:
    "bg-[var(--ds-warning-surface)] text-[var(--ds-warning)] ring-[color-mix(in_oklab,var(--ds-warning)_35%,transparent)]",
  danger:
    "bg-[var(--ds-danger-surface)] text-[var(--ds-danger)] ring-[color-mix(in_oklab,var(--ds-danger)_35%,transparent)]",
};

export function Badge({
  tone = "neutral",
  className,
  ...props
}: HTMLAttributes<HTMLSpanElement> & { tone?: StatusTone }) {
  return (
    <span
      className={cn(
        "inline-flex h-5 items-center gap-1 rounded-[6px] px-2 text-[10.5px] font-semibold uppercase tracking-[.04em] ring-1 ring-inset",
        tones[tone],
        className,
      )}
      {...props}
    />
  );
}

export function Chip({
  className,
  tone = "neutral",
  ...props
}: HTMLAttributes<HTMLSpanElement> & { tone?: StatusTone }) {
  return (
    <span
      className={cn(
        "inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-[11.5px] font-medium ring-1 ring-inset",
        tones[tone],
        className,
      )}
      {...props}
    />
  );
}

const dotMap: Record<"online" | "offline" | "warning" | "error", string> = {
  online: "bg-[var(--ds-success)] shadow-[0_0_0_3px_color-mix(in_oklab,var(--ds-success)_28%,transparent)]",
  offline: "bg-[var(--ds-text-subtle)]",
  warning: "bg-[var(--ds-accent)] shadow-[0_0_0_3px_var(--ds-accent-surface)]",
  error: "bg-[var(--ds-danger)] shadow-[0_0_0_3px_var(--ds-danger-surface)]",
};

export function StatusIndicator({
  status,
  label,
  className,
}: {
  status: "online" | "offline" | "warning" | "error";
  label?: string;
  className?: string;
}) {
  return (
    <span className={cn("inline-flex items-center gap-2 text-xs text-[var(--ds-text-muted)]", className)}>
      <span aria-hidden className={cn("h-2 w-2 rounded-full", dotMap[status])} />
      <span className="capitalize">{label ?? status}</span>
    </span>
  );
}
