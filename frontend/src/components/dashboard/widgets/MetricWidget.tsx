import { Metric } from "../../ui";

/*
  KPI/Metric widget — compact hierarchy: label eyebrow, big value, subtle trend chip.
  Uses tabular-nums for stable telemetry alignment.
*/
export default function MetricWidget({
  value,
  label,
  unit,
  trend,
}: {
  value: number | string | null;
  label?: string;
  unit?: string;
  trend?: string;
}) {
  const trendPositive = trend?.trim().startsWith("+");
  const trendNegative = trend?.trim().startsWith("-");
  return (
    <div className="flex h-full flex-col justify-center gap-2">
      {label && (
        <p className="text-[10.5px] font-semibold uppercase tracking-[.1em] text-[var(--ds-text-subtle)]">
          {label}
        </p>
      )}
      <Metric value={value ?? "—"} unit={value !== null ? unit : undefined} />
      {trend && (
        <span
          className={
            "inline-flex w-fit items-center gap-1 rounded-[6px] px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset " +
            (trendPositive
              ? "bg-[var(--ds-success-surface)] text-[var(--ds-success)] ring-[color-mix(in_oklab,var(--ds-success)_30%,transparent)]"
              : trendNegative
                ? "bg-[var(--ds-danger-surface)] text-[var(--ds-danger)] ring-[color-mix(in_oklab,var(--ds-danger)_30%,transparent)]"
                : "bg-[var(--ds-primary-surface)] text-[var(--ds-primary)] ring-[var(--ds-primary-outline)]")
          }
        >
          {trend}
        </span>
      )}
    </div>
  );
}
