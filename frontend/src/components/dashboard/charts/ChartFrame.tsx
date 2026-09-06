import type { TelemetryRecord } from "../../../types/telemetry";
import { ErrorState, LoadingState } from "../../ui";

const WIDTH = 720;
const HEIGHT = 240;
const PAD_X = 58;
const PAD_TOP = 34;
const PAD_BOTTOM = 48;

/*
  Chart frame v2 — premium data visualization (refs 4/5/9):
  gradient area fill, glowing stroke, subtle plotting grid,
  gradient bars with rounded caps, soft dot markers.
*/
export default function ChartFrame({ records, type, loading = false, error }: { records: TelemetryRecord[]; type: "line" | "area" | "bar"; loading?: boolean; error?: string }) {
  if (loading) return <LoadingState label="Loading chart..." className="h-full" />;
  if (error) return <ErrorState title="Chart unavailable" description={error} className="h-full min-h-0" />;
  if (!records.length) return <div className="flex h-full min-h-0 items-center justify-center text-xs text-[var(--ds-text-subtle)]">No telemetry data.</div>;

  const values = records.map((record) => record.value);
  const barColors = ["var(--ds-chart-1)", "var(--ds-chart-2)", "var(--ds-chart-4)", "var(--ds-chart-3)", "var(--ds-chart-6)"];
  const minimum = Math.min(...values);
  const maximum = Math.max(...values);
  const span = maximum - minimum || 1;
  const plotWidth = WIDTH - PAD_X * 2;
  const plotHeight = HEIGHT - PAD_TOP - PAD_BOTTOM;
  const plotBottom = HEIGHT - PAD_BOTTOM;
  const x = (index: number) => PAD_X + (records.length === 1 ? plotWidth / 2 : index * plotWidth / (records.length - 1));
  const y = (value: number) => PAD_TOP + (maximum - value) * plotHeight / span;
  const points = records.map((record, index) => `${x(index)},${y(record.value)}`).join(" ");
  const description = `${type} chart. ${records.length} telemetry points from ${minimum} to ${maximum}.`;
  const ticks = [0, 1, 2, 3, 4];
  const dates = [records[0], records[Math.floor((records.length - 1) / 2)], records.at(-1)!];
  const lastPoint = records[records.length - 1];

  return (
    <figure className="h-full min-h-0" aria-label={description}>
      <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="h-full w-full" role="img">
        <title>{description}</title>
        <defs>
          <linearGradient id="iot-area-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--ds-chart-1)" stopOpacity=".55" />
            <stop offset="55%" stopColor="var(--ds-chart-1)" stopOpacity=".18" />
            <stop offset="100%" stopColor="var(--ds-chart-1)" stopOpacity="0" />
          </linearGradient>
          <linearGradient id="iot-line-stroke" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stopColor="var(--ds-chart-1)" />
            <stop offset="70%" stopColor="var(--ds-primary-soft)" />
            <stop offset="100%" stopColor="var(--ds-chart-3)" />
          </linearGradient>
          <filter id="iot-line-glow" x="-30%" y="-30%" width="160%" height="160%">
            <feGaussianBlur stdDeviation="4.5" result="blur" />
            <feMerge>
              <feMergeNode in="blur" />
              <feMergeNode in="SourceGraphic" />
            </feMerge>
          </filter>
        </defs>
        {/* plotting dots at grid intersections */}
        {ticks.map((step) => [0, 1, 2, 3, 4].map((col) => (
          <circle key={`${step}-${col}`} cx={PAD_X + (col * plotWidth) / 4} cy={PAD_TOP + (step * plotHeight) / 4} r="1.4" fill="var(--ds-text-muted)" opacity=".5" />
        )))}
        {ticks.map((step) => (
          <g key={step}>
            <line x1={PAD_X} x2={WIDTH - PAD_X} y1={PAD_TOP + (step * plotHeight) / 4} y2={PAD_TOP + (step * plotHeight) / 4} stroke="var(--ds-chart-grid)" strokeWidth="1.5" strokeDasharray="3 5" opacity=".9" />
            <text x={PAD_X - 10} y={PAD_TOP + (step * plotHeight) / 4 + 5} textAnchor="end" fill="var(--ds-text)" fontSize="15" fontWeight="700">
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
                  height={plotBottom - y(record.value)}
                  rx="3"
                  fill={barColors[index % barColors.length]}
                  opacity={index === records.length - 1 ? 1 : 0.8}
                  style={{ filter: `drop-shadow(0 0 4px color-mix(in oklab, ${barColors[index % barColors.length]} 55%, transparent))` }}
                />
                <title>{`${record.value}${record.unit ? ` ${record.unit}` : ""} — ${new Date(record.recordedAt).toLocaleString()}`}</title>
                {(records.length <= 12 || index === records.length - 1) && (
                  <text x={x(index)} y={Math.max(PAD_TOP + 14, y(record.value) - 7)} textAnchor="middle" fill="var(--ds-text)" fontSize="14" fontWeight="700">
                    {record.value}
                  </text>
                )}
                {index === records.length - 1 && (
                  <rect
                    x={x(index) - barWidth / 2}
                    y={y(record.value)}
                    width={barWidth}
                    height={3}
                    rx="1.5"
                    fill={barColors[index % barColors.length]}
                    style={{ filter: "brightness(1.25)" }}
                  />
                )}
              </g>
            );
          })
        ) : (
          <>
            {type === "area" && (
              <polygon points={`${PAD_X},${plotBottom} ${points} ${WIDTH - PAD_X},${plotBottom}`} fill="url(#iot-area-fill)" />
            )}
            <polyline
              points={points}
              fill="none"
              stroke="url(#iot-line-stroke)"
              strokeWidth="3.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              filter="url(#iot-line-glow)"
            />
            {records.map((record, index) => (
              <circle
                key={`point-${record.id}`}
                cx={x(index)}
                cy={y(record.value)}
                r={records.length <= 24 ? 3 : 5}
                fill={records.length <= 24 ? "var(--ds-chart-1)" : "transparent"}
                stroke={records.length <= 24 ? "var(--ds-card)" : "transparent"}
                strokeWidth="1.5"
              >
                <title>{`${record.value}${record.unit ? ` ${record.unit}` : ""} — ${new Date(record.recordedAt).toLocaleString()}`}</title>
              </circle>
            ))}
            {type === "line" && (
              <g>
                <circle cx={x(records.length - 1)} cy={y(lastPoint.value)} r="11" fill="var(--ds-chart-3)" opacity=".22" />
                <circle cx={x(records.length - 1)} cy={y(lastPoint.value)} r="6" fill="none" stroke="var(--ds-chart-3)" strokeWidth="1.5" opacity=".8" />
                <circle cx={x(records.length - 1)} cy={y(lastPoint.value)} r="3.5" fill="var(--ds-chart-3)" stroke="var(--ds-card)" strokeWidth="1.5" />
              </g>
            )}
            <g>
              <rect x={Math.min(WIDTH - PAD_X - 66, Math.max(PAD_X, x(records.length - 1) - 33))} y={Math.max(4, y(lastPoint.value) - 30)} width="66" height="22" rx="6" fill="var(--ds-card-alt)" stroke="var(--ds-chart-3)" />
              <text x={Math.min(WIDTH - PAD_X - 33, Math.max(PAD_X + 33, x(records.length - 1)))} y={Math.max(19, y(lastPoint.value) - 15)} textAnchor="middle" fill="var(--ds-text)" fontSize="14" fontWeight="700">
                {lastPoint.value}{lastPoint.unit ? ` ${lastPoint.unit}` : ""}
              </text>
            </g>
          </>
        )}
        {dates.map((record, index) => (
          <text
            key={`${record.id}-${index}`}
            x={PAD_X + (index * plotWidth) / 2}
            y={HEIGHT - 14}
            textAnchor={index === 0 ? "start" : index === 2 ? "end" : "middle"}
            fill="var(--ds-text)"
            fontSize="14"
            fontWeight="700"
          >
            {new Date(record.recordedAt).toLocaleDateString(undefined, { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" })}
          </text>
        ))}
      </svg>
      <figcaption className="sr-only">{description}</figcaption>
    </figure>
  );
}
