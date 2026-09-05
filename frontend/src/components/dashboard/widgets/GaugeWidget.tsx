/*
  Gauge — precision ring with subtle glow at the fill edge.
  Ring uses --ds-primary; track is inset. Center value in tabular numerals.
*/
export default function GaugeWidget({
  value,
  min = 0,
  max = 100,
  unit,
}: {
  value: number;
  min?: number;
  max?: number;
  unit?: string;
}) {
  const percent = Math.max(0, Math.min(100, ((value - min) / (max - min || 1)) * 100));
  return (
    <div className="flex h-full items-center justify-center">
      <div
        className="relative grid h-28 w-28 place-items-center rounded-full"
        role="img"
        aria-label={`${value}${unit ?? ""}, ${Math.round(percent)} percent of range`}
        style={{
          background: `conic-gradient(var(--ds-primary) ${percent}%, var(--ds-card-alt) 0)`,
          boxShadow:
            "0 0 0 1px var(--ds-border-subtle), 0 0 20px -6px color-mix(in oklab, var(--ds-primary) 45%, transparent)",
        }}
      >
        <div
          className="grid h-[86px] w-[86px] place-items-center rounded-full bg-[var(--ds-card)]"
          style={{
            boxShadow:
              "inset 0 1px 2px var(--ds-inset-shadow), inset 0 0 0 1px var(--ds-border-subtle)",
          }}
        >
          <span
            className="flex flex-col items-center gap-0.5 tabular-nums"
            data-numeric
          >
            <span className="text-[20px] font-semibold leading-none text-[var(--ds-text)]">
              {value}
              {unit && (
                <span className="ml-0.5 text-xs font-medium text-[var(--ds-text-muted)]">
                  {unit}
                </span>
              )}
            </span>
            <span className="text-[10px] font-medium uppercase tracking-[.1em] text-[var(--ds-text-subtle)]">
              {Math.round(percent)}%
            </span>
          </span>
        </div>
      </div>
    </div>
  );
}
