import { TrendingDown, TrendingUp, Minus } from "lucide-react";

/*
  KPI widget v2 — purpose-built composition (ref 5 + 8):
  accent eyebrow, luminous big value with gradient text, tone-aware
  trend chip with directional icon, subtle bottom accent line.
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
    ? "text-[var(--ds-success)] bg-[var(--ds-success-surface)]"
    : negative
      ? "text-[var(--ds-danger)] bg-[var(--ds-danger-surface)]"
      : "text-[var(--ds-primary)] bg-[var(--ds-primary-surface)]";
  return (
    <div className="relative flex h-full flex-col justify-center gap-1.5">
      {label && (
        <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-[var(--ds-text-subtle)]">
          {label}
        </p>
      )}
      <p
        className="flex items-baseline gap-1.5 text-[30px] font-bold leading-none tracking-tight tabular-nums"
        data-numeric
        style={{
          background: "linear-gradient(135deg, var(--ds-text) 30%, var(--ds-primary-soft))",
          WebkitBackgroundClip: "text",
          backgroundClip: "text",
          color: "transparent",
        }}
      >
        {value ?? "—"}
        {value !== null && unit && (
          <span
            className="text-sm font-medium"
            style={{ color: "var(--ds-text-muted)", WebkitBackgroundClip: "initial", background: "none" }}
          >
            {unit}
          </span>
        )}
      </p>
      {trend && (
        <span className={`inline-flex w-fit items-center gap-1 rounded-[6px] px-1.5 py-0.5 text-[11px] font-semibold ${tone}`}>
          <TrendIcon size={11} aria-hidden />
          {trend}
        </span>
      )}
      <span
        aria-hidden
        className="absolute inset-x-0 bottom-0 h-[2px] rounded-full opacity-70"
        style={{
          background:
            "linear-gradient(90deg, var(--ds-primary), var(--ds-primary-soft) 55%, transparent)",
          maskImage: "linear-gradient(90deg, black 0%, transparent 75%)",
          WebkitMaskImage: "linear-gradient(90deg, black 0%, transparent 75%)",
        }}
      />
    </div>
  );
}
