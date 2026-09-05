import type { HTMLAttributes } from "react";
import { cn } from "../../utils/cn";

/*
  Typography scale — compact, technical, precise.
  Page titles are restrained (not oversized). Numeric telemetry uses tabular-nums.
*/
export function PageTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
  return (
    <h1
      className={cn(
        "text-[20px] font-semibold tracking-tight text-[var(--ds-text)] sm:text-[22px]",
        className,
      )}
      {...props}
    />
  );
}

export function SectionTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
  return (
    <h2
      className={cn("text-[15px] font-semibold text-[var(--ds-text)]", className)}
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
        "text-[11px] font-medium uppercase tracking-[.08em] text-[var(--ds-text-muted)]",
        className,
      )}
      {...props}
    />
  );
}

/* Metric — large numeric with unit slot. Tabular numerals; restrained size. */
export function Metric({
  value,
  unit,
  className,
  ...props
}: { value: string | number; unit?: string } & HTMLAttributes<HTMLSpanElement>) {
  return (
    <span
      className={cn(
        "inline-flex items-baseline gap-1 text-[26px] font-semibold leading-none tracking-tight text-[var(--ds-text)] tabular-nums",
        className,
      )}
      data-numeric
      {...props}
    >
      {value}
      {unit && <span className="text-sm font-medium text-[var(--ds-text-muted)]">{unit}</span>}
    </span>
  );
}
