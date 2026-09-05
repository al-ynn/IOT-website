/*
  Gauge v2 — premium radial instrument (refs 5/6/8):
  outer bezel ring, gradient arc with glow, tick marks, recessed dial,
  gradient center value + percent readout.
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
  const ticks = Array.from({ length: 24 }, (_, i) => (i * 360) / 24);
  return (
    <div className="flex h-full items-center justify-center">
      <div
        className="relative grid h-32 w-32 place-items-center rounded-full"
        role="img"
        aria-label={`${value}${unit ?? ""}, ${Math.round(percent)} percent of range`}
        style={{
          background: "var(--ds-card-alt)",
          boxShadow:
            "inset 0 1px 2px var(--ds-inset-shadow), 0 1px 0 var(--ds-inset-highlight)",
        }}
      >
        {/* tick marks */}
        {ticks.map((deg, i) => (
          <span
            key={deg}
            aria-hidden
            className="absolute left-1/2 top-1/2 h-[3px] w-[1.5px] rounded-full"
            style={{
              background:
                i <= (percent / 100) * 24
                  ? "var(--ds-primary-soft)"
                  : "color-mix(in oklab, var(--ds-text-subtle) 40%, transparent)",
              transform: `rotate(${deg}deg) translateY(-60px)`,
              opacity: i % 6 === 0 ? 1 : 0.5,
            }}
          />
        ))}
        {/* gradient arc */}
        <div
          aria-hidden
          className="absolute inset-[7px] rounded-full"
          style={{
            background: `conic-gradient(from 180deg, var(--ds-primary-strong), var(--ds-primary) ${percent * 0.75}%, var(--ds-primary-soft) ${percent}%, transparent ${percent}%)`,
            boxShadow: "0 0 18px -4px var(--ds-primary-glow)",
            mask: "radial-gradient(farthest-side, transparent calc(100% - 8px), black calc(100% - 7px))",
            WebkitMask: "radial-gradient(farthest-side, transparent calc(100% - 8px), black calc(100% - 7px))",
          }}
        />
        {/* recessed dial */}
        <div
          className="grid h-[92px] w-[92px] place-items-center rounded-full"
          style={{
            background: "var(--ds-card)",
            boxShadow:
              "inset 0 2px 4px var(--ds-inset-shadow), inset 0 0 0 1px var(--ds-border-subtle)",
          }}
        >
          <span className="flex flex-col items-center gap-0.5 tabular-nums" data-numeric>
            <span
              className="text-[22px] font-bold leading-none"
              style={{
                background: "linear-gradient(135deg, var(--ds-text), var(--ds-primary-soft))",
                WebkitBackgroundClip: "text",
                backgroundClip: "text",
                color: "transparent",
              }}
            >
              {value}
              {unit && (
                <span className="ml-0.5 text-[11px] font-medium" style={{ color: "var(--ds-text-muted)", WebkitBackgroundClip: "initial", background: "none" }}>
                  {unit}
                </span>
              )}
            </span>
            <span className="text-[9px] font-semibold uppercase tracking-[0.18em] text-[var(--ds-text-subtle)]">
              {Math.round(percent)}% load
            </span>
          </span>
        </div>
      </div>
    </div>
  );
}
