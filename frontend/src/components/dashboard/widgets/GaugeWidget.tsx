/*
  Gauge v3 — neon radial instrument:
  glowing bezel ring, luminous gradient arc with bloom, tick marks,
  recessed dark dial, glowing center value.
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
          background: "var(--ds-bg)",
          boxShadow:
            "0 0 0 1px var(--ds-border-luminous), 0 0 26px -6px var(--ds-primary-glow), inset 0 2px 6px rgb(0 0 0 / .5)",
        }}
      >
        {ticks.map((deg, i) => {
          const threshold = (i / 24) * 100;
          const lit = threshold <= percent;
          const hot = threshold >= 78;
          return (
          <span
            key={deg}
            aria-hidden
            className="absolute left-1/2 top-1/2 h-[3px] w-[1.5px] rounded-full"
            style={{
              background: lit ? (hot ? "var(--ds-chart-3)" : "var(--ds-primary-soft)") : "color-mix(in oklab, var(--ds-text-subtle) 35%, transparent)",
              boxShadow: lit ? `0 0 4px ${hot ? "var(--ds-chart-3)" : "var(--ds-primary-glow)"}` : "none",
              transform: `rotate(${deg}deg) translateY(-60px)`,
              opacity: i % 6 === 0 ? 1 : 0.5,
            }}
          />
          );
        })}
        <div
          aria-hidden
          className="absolute inset-[7px] rounded-full"
          style={{
            background: `conic-gradient(from 180deg, var(--ds-primary-strong), var(--ds-primary) ${percent * 0.55}%, var(--ds-primary-soft) ${percent * 0.85}%, var(--ds-chart-3) ${percent}%, transparent ${percent}%)`,
            filter: "drop-shadow(0 0 7px var(--ds-primary-glow))",
            mask: "radial-gradient(farthest-side, transparent calc(100% - 8px), black calc(100% - 7px))",
            WebkitMask: "radial-gradient(farthest-side, transparent calc(100% - 8px), black calc(100% - 7px))",
          }}
        />
        <div
          className="grid h-[92px] w-[92px] place-items-center rounded-full"
          style={{
            background: "radial-gradient(circle at 50% 35%, color-mix(in oklab, var(--ds-primary) 12%, var(--ds-card)), var(--ds-card))",
            boxShadow: "inset 0 2px 6px rgb(0 0 0 / .45), inset 0 0 0 1px var(--ds-border-subtle)",
          }}
        >
          <span className="flex flex-col items-center gap-0.5 tabular-nums" data-numeric>
            <span
              className="text-[22px] font-bold leading-none text-[var(--ds-text)]"
              style={{ textShadow: "0 0 14px var(--ds-primary-glow)" }}
            >
              {value}
              {unit && (
                <span className="ml-0.5 text-[11px] font-semibold text-[var(--ds-primary-soft)]">{unit}</span>
              )}
            </span>
            <span className="text-[9px] font-bold uppercase tracking-[0.22em] text-[var(--ds-chart-3)]" style={{ textShadow: "0 0 10px var(--ds-chart-3)" }}>
              {Math.round(percent)}%
            </span>
          </span>
        </div>
      </div>
    </div>
  );
}
