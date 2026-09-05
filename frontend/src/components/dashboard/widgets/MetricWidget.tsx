import { TrendingDown, TrendingUp, Minus, Activity } from "lucide-react";

/*
  KPI widget v4 — vibrant stat block (reference 3 structure):
  glowing colored icon chip → eyebrow → glowing number → trend chip.
  Uses .kpi-* container-query classes: icon drops and text compacts
  automatically at the smallest widget sizes.
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
    ? "text-[var(--ds-chart-2)] border-[color-mix(in_oklab,var(--ds-chart-2)_45%,transparent)]"
    : negative
      ? "text-[var(--ds-chart-5)] border-[color-mix(in_oklab,var(--ds-chart-5)_45%,transparent)]"
      : "text-[var(--ds-primary)] border-[var(--ds-primary-outline)]";
  return (
    <div className="kpi-row">
      <span
        aria-hidden
        className="big-ring relative grid shrink-0 place-items-center rounded-full"
        style={{
          background: "conic-gradient(var(--ds-chart-1) 0 68%, var(--ds-card-alt) 68% 100%)",
          boxShadow: "0 0 26px -6px var(--ds-primary-glow), inset 0 1px 2px var(--ds-inset-shadow)",
        }}
      >
        <span
          className="big-ring-core grid place-items-center rounded-full text-[var(--ds-primary-soft)]"
          style={{ background: "var(--ds-card)", boxShadow: "inset 0 0 0 1px var(--ds-primary-outline), inset 0 1px 0 rgb(255 255 255 / .08)" }}
        >
          <Activity />
        </span>
      </span>
      <div className="flex min-w-0 flex-col gap-1">
        {label && (
          <p className="truncate text-[10px] font-bold uppercase tracking-[0.2em] text-[var(--ds-text-subtle)]">
            {label}
          </p>
        )}
        <p
          className="kpi-value flex items-baseline gap-1.5"
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
