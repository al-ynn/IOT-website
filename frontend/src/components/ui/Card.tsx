import type { HTMLAttributes, ReactNode } from "react";
import { cn } from "../../utils/cn";

export type CardVariant = "default" | "elevated" | "interactive" | "glass" | "inset" | "accent";

/*
  Card v2 — Nova Glass / Nexus surface language:
  glass layering, luminous borders, ambient corner glows, hover lift.
*/
const variants: Record<CardVariant, string> = {
  default:
    "border border-[var(--ds-border-subtle)] bg-[var(--ds-card)]",
  elevated:
    "border border-[var(--ds-border-subtle)] bg-[var(--ds-card)] " +
    "shadow-[var(--ds-shadow-md)]",
  interactive:
    "border border-[var(--ds-border-subtle)] bg-[var(--ds-card)] " +
    "transition hover:-translate-y-0.5 hover:border-[var(--ds-primary-outline)] " +
    "hover:shadow-[var(--ds-glow-soft),var(--ds-shadow-md)]",
  glass:
    "border border-[var(--ds-border-subtle)] surface-glass",
  inset:
    "border border-[var(--ds-border-subtle)] bg-[var(--ds-card-alt)] " +
    "shadow-[inset_0_1px_2px_var(--ds-inset-shadow)]",
  accent:
    "border border-[var(--ds-primary-outline)] bg-[var(--ds-card)] " +
    "shadow-[var(--ds-glow-primary)]",
};

export function Card({
  className,
  variant = "default",
  ...props
}: HTMLAttributes<HTMLDivElement> & { variant?: CardVariant }) {
  return (
    <div
      className={cn("rounded-[14px] relative overflow-hidden", variants[variant], className)}
      {...props}
    />
  );
}

export function CardHeader({
  title,
  description,
  action,
  className,
  eyebrow,
}: {
  title: ReactNode;
  description?: ReactNode;
  action?: ReactNode;
  eyebrow?: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "relative flex items-start justify-between gap-3 border-b border-[var(--ds-border-subtle)] px-4 py-3",
        className,
      )}
      style={{
        background:
          "linear-gradient(180deg, color-mix(in oklab, var(--ds-surface-elevated) 55%, transparent), transparent)",
      }}
    >
      <div className="min-w-0">
        {eyebrow && (
          <p className="mb-1 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--ds-primary)]">
            <span aria-hidden className="h-1 w-1 rounded-full bg-[var(--ds-primary)]" style={{ boxShadow: "0 0 5px 1px var(--ds-primary-glow)" }} />
            {eyebrow}
          </p>
        )}
        <h3 className="truncate text-[13px] font-semibold text-[var(--ds-text)]">
          {title}
        </h3>
        {description && (
          <p className="mt-0.5 text-xs text-[var(--ds-text-muted)]">{description}</p>
        )}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  );
}

export function CardContent({
  className,
  ...props
}: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("p-4", className)} {...props} />;
}

export function CardFooter({
  className,
  ...props
}: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn(
        "flex items-center justify-between gap-2 border-t border-[var(--ds-border-subtle)] bg-[var(--ds-card-alt)] px-4 py-3",
        className,
      )}
      {...props}
    />
  );
}
