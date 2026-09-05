import { TrendingDown, TrendingUp, Minus, Activity } from "lucide-react";

/*
  KPI widget v3 — neon stat block (reference structure):
  glowing icon chip → eyebrow label → HUGE glowing number → trend chip.
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
  const positive = trend?.trim().startsWith("+");
  const negative = trend?.trim().startsWith("-");
  const TrendIcon = positive ? TrendingUp : negative ? TrendingDown : Minus;
  const tone = positive
    ? "text-[var(--ds-success)] border-[color-mix(in_oklab,var(--ds-success)_45%,transparent)]"
    : negative
      ? "text-[var(--ds-danger)] border-[color-mix(in_oklab,var(--ds-danger)_45%,transparent)]"
      : "text-[var(--ds-primary)] border-[var(--ds-primary-outline)]";
  return (
    <div className="flex h-full items-center gap-4">
      <span
        aria-hidden
        className="grid h-12 w-12 shrink-0 place-items-center rounded-[12px] text-[var(--ds-primary-soft)]"
        style={{
          background: "linear-gradient(145deg, var(--ds-primary-surface), color-mix(in oklab, var(--ds-primary) 30%, transparent))",
          boxShadow:
            "inset 0 0 0 1px var(--ds-primary-outline), 0 0 22px -4px var(--ds-primary-glow)",
        }}
      >
        <Activity size={20} />
      </span>
      <div className="flex min-w-0 flex-col gap-1">
        {label && (
          <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-[var(--ds-text-subtle)]">
            {label}
          </p>
        )}
        <p
          className="flex items-baseline gap-1.5 text-[32px] font-bold leading-none tracking-tight tabular-nums text-[var(--ds-text)]"
          data-numeric
          style={{ textShadow: "0 0 18px var(--ds-primary-glow), 0 0 4px rgb(255 255 255 / .15)" }}
        >
          {value ?? "—"}
          {value !== null && unit && (
            <span className="text-sm font-semibold text-[var(--ds-primary-soft)]">{unit}</span>
          )}
        </p>
        {trend && (
          <span
            className={`inline-flex w-fit items-center gap-1 rounded-full border bg-[var(--ds-card-alt)] px-2 py-0.5 text-[11px] font-bold ${tone}`}
            style={{ boxShadow: "0 0 12px -4px currentColor" }}
          >
            <TrendIcon size={11} aria-hidden />
            {trend}
          </span>
        )}
      </div>
    </div>
  );
}
