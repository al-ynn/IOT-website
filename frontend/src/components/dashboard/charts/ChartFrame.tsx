import type { TelemetryRecord } from "../../../types/telemetry";
import { ErrorState, LoadingState } from "../../ui";

const WIDTH = 720;
const HEIGHT = 240;
const PAD = 44;

/*
  Chart frame v2 — premium data visualization (refs 4/5/9):
  gradient area fill, glowing stroke, subtle plotting grid,
  gradient bars with rounded caps, soft dot markers.
*/
export default function ChartFrame({ records, type, loading = false, error }: { records: TelemetryRecord[]; type: "line" | "area" | "bar"; loading?: boolean; error?: string }) {
  if (loading) return <LoadingState label="Loading chart..." className="h-full" />;
  if (error) return <ErrorState title="Chart unavailable" description={error} className="h-full min-h-24" />;
  if (!records.length) return <div className="flex h-full min-h-24 items-center justify-center text-xs text-[var(--ds-text-subtle)]">No telemetry data.</div>;

  const values = records.map((record) => record.value);
  const minimum = Math.min(...values);
  const maximum = Math.max(...values);
  const span = maximum - minimum || 1;
  const plotWidth = WIDTH - PAD * 2;
  const plotHeight = HEIGHT - PAD * 2;
  const x = (index: number) => PAD + (records.length === 1 ? plotWidth / 2 : index * plotWidth / (records.length - 1));
  const y = (value: number) => PAD + (maximum - value) * plotHeight / span;
  const points = records.map((record, index) => `${x(index)},${y(record.value)}`).join(" ");
  const description = `${type} chart. ${records.length} telemetry points from ${minimum} to ${maximum}.`;
  const ticks = [0, 1, 2, 3, 4];
  const dates = [records[0], records[Math.floor((records.length - 1) / 2)], records.at(-1)!];
  const lastPoint = records[records.length - 1];

  return (
    <figure className="h-full min-h-32" aria-label={description}>
      <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="h-full w-full" role="img">
        <title>{description}</title>
        <defs>
          <linearGradient id="iot-area-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--ds-primary)" stopOpacity=".38" />
            <stop offset="100%" stopColor="var(--ds-primary)" stopOpacity="0" />
          </linearGradient>
          <linearGradient id="iot-line-stroke" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stopColor="var(--ds-primary-strong)" />
            <stop offset="60%" stopColor="var(--ds-primary)" />
            <stop offset="100%" stopColor="var(--ds-primary-soft)" />
          </linearGradient>
          <linearGradient id="iot-bar-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--ds-primary-soft)" />
            <stop offset="100%" stopColor="var(--ds-primary-strong)" />
          </linearGradient>
          <filter id="iot-line-glow" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur stdDeviation="3.5" result="blur" />
            <feMerge>
              <feMergeNode in="blur" />
              <feMergeNode in="SourceGraphic" />
            </feMerge>
          </filter>
        </defs>
        {ticks.map((step) => (
          <g key={step}>
            <line x1={PAD} x2={WIDTH - PAD} y1={PAD + (step * plotHeight) / 4} y2={PAD + (step * plotHeight) / 4} stroke="var(--ds-chart-grid)" strokeDasharray="2 5" />
            <text x={PAD - 7} y={PAD + (step * plotHeight) / 4 + 4} textAnchor="end" fill="currentColor" className="text-[10px] text-[var(--ds-text-subtle)]">
              {(maximum - (step * span) / 4).toFixed(span < 10 ? 1 : 0)}
            </text>
          </g>
        ))}
        {type === "bar" ? (
          records.map((record, index) => {
            const barWidth = Math.max(3, (plotWidth / records.length) * 0.6);
            return (
              <g key={record.id}>
                <rect
                  x={x(index) - barWidth / 2}
                  y={y(record.value)}
                  width={barWidth}
                  height={HEIGHT - PAD - y(record.value)}
                  rx="3"
                  fill="url(#iot-bar-fill)"
                  opacity={index === records.length - 1 ? 1 : 0.72}
                />
                {index === records.length - 1 && (
                  <rect
                    x={x(index) - barWidth / 2}
                    y={y(record.value)}
                    width={barWidth}
                    height={3}
                    rx="1.5"
                    fill="var(--ds-primary-soft)"
                  />
                )}
              </g>
            );
          })
        ) : (
          <>
            {type === "area" && (
              <polygon points={`${PAD},${HEIGHT - PAD} ${points} ${WIDTH - PAD},${HEIGHT - PAD}`} fill="url(#iot-area-fill)" />
            )}
            <polyline
              points={points}
              fill="none"
              stroke="url(#iot-line-stroke)"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              filter="url(#iot-line-glow)"
            />
            {type === "line" && (
              <g>
                <circle cx={x(records.length - 1)} cy={y(lastPoint.value)} r="8" fill="var(--ds-primary)" opacity=".18" />
                <circle cx={x(records.length - 1)} cy={y(lastPoint.value)} r="3.5" fill="var(--ds-primary)" stroke="var(--ds-card)" strokeWidth="1.5" />
              </g>
            )}
          </>
        )}
        {dates.map((record, index) => (
          <text
            key={`${record.id}-${index}`}
            x={PAD + (index * plotWidth) / 2}
            y={HEIGHT - 10}
            textAnchor={index === 0 ? "start" : index === 2 ? "end" : "middle"}
            fill="currentColor"
            className="text-[10px] text-[var(--ds-text-subtle)]"
          >
            {new Date(record.recordedAt).toLocaleDateString(undefined, { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" })}
          </text>
        ))}
      </svg>
      <figcaption className="sr-only">{description}</figcaption>
    </figure>
  );
}
