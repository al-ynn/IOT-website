import type { HTMLAttributes } from "react";
import { cn } from "../../utils/cn";

/*
  Typography v2 — command-center hierarchy (refs: Nexus / Nova Glass):
  gradient-accent page titles with luminous underline, compact sections,
  tabular telemetry. Restrained sizes (no chunky headings).
*/
export function PageTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
  return (
    <h1
      className={cn(
        "w-fit text-[20px] font-bold tracking-tight text-[var(--ds-text)] sm:text-[22px]",
        className,
      )}
      style={{
        background: "linear-gradient(120deg, var(--ds-text) 55%, var(--ds-primary-soft))",
        WebkitBackgroundClip: "text",
        backgroundClip: "text",
        color: "transparent",
      }}
      {...props}
    />
  );
}

export function SectionTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
  return (
    <h2
      className={cn(
        "flex items-center gap-2 text-[13px] font-semibold uppercase tracking-[0.12em] text-[var(--ds-text)]",
        className,
      )}
      {...props}
    />
  );
}

export function BodyText({ className, ...props }: HTMLAttributes<HTMLParagraphElement>) {
  return (
    <p
      className={cn("text-sm leading-5 text-[var(--ds-text-muted)]", className)}
      {...props}
    />
  );
}

export function Caption({ className, ...props }: HTMLAttributes<HTMLParagraphElement>) {
  return (
    <p
      className={cn("text-xs leading-4 text-[var(--ds-text-subtle)]", className)}
      {...props}
    />
  );
}

export function Label({ className, ...props }: HTMLAttributes<HTMLSpanElement>) {
  return (
    <span
      className={cn(
        "text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[var(--ds-text-muted)]",
        className,
      )}
      {...props}
    />
  );
}

/* Eyebrow — tiny technical overline with glowing dot (Nexus/Nova style) */
export function Eyebrow({ className, ...props }: HTMLAttributes<HTMLSpanElement>) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-[var(--ds-primary)]",
        className,
      )}
      {...props}
    >
      <span
        aria-hidden
        className="h-1.5 w-1.5 rounded-full bg-[var(--ds-primary)]"
        style={{ boxShadow: "0 0 6px 1px var(--ds-primary-glow)" }}
      />
      {props.children}
    </span>
  );
}

/* Metric — gradient numeric with unit slot; tabular numerals */
export function Metric({
  value,
  unit,
  className,
  ...props
}: { value: string | number; unit?: string } & HTMLAttributes<HTMLSpanElement>) {
  return (
    <span
      className={cn("inline-flex items-baseline gap-1 text-[26px] font-bold leading-none tracking-tight tabular-nums", className)}
      data-numeric
      style={{
        background: "linear-gradient(135deg, var(--ds-text) 40%, var(--ds-primary-soft))",
        WebkitBackgroundClip: "text",
        backgroundClip: "text",
        color: "transparent",
      }}
      {...props}
    >
      {value}
      {unit && (
        <span className="text-sm font-medium text-[var(--ds-text-muted)]" style={{ WebkitBackgroundClip: "initial", background: "none", color: "var(--ds-text-muted)" }}>
          {unit}
        </span>
      )}
    </span>
  );
}
