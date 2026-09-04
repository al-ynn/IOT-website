import type { TelemetryRecord } from "../../../types/telemetry";
import { ErrorState, LoadingState } from "../../ui";

const WIDTH = 720;
const HEIGHT = 240;
const PAD = 44;

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
  const ticks=[0,1,2,3,4];
  const dates=[records[0],records[Math.floor((records.length-1)/2)],records.at(-1)!];

  return <figure className="h-full min-h-32" aria-label={description}>
    <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="h-full w-full" role="img">
      <title>{description}</title>
      {ticks.map((step) => <g key={step}><line x1={PAD} x2={WIDTH-PAD} y1={PAD+step*plotHeight/4} y2={PAD+step*plotHeight/4} stroke="currentColor" className="text-[var(--ds-border)]"/><text x={PAD-7} y={PAD+step*plotHeight/4+4} textAnchor="end" fill="currentColor" className="text-[10px] text-[var(--ds-text-muted)]">{(maximum-step*span/4).toFixed(span<10?1:0)}</text></g>)}
      {type === "bar" ? records.map((record, index) => {
        const barWidth = Math.max(2, plotWidth / records.length * .65);
        return <rect key={record.id} x={x(index)-barWidth/2} y={y(record.value)} width={barWidth} height={HEIGHT-PAD-y(record.value)} rx="2" fill="var(--ds-primary)" />;
      }) : <>
        {type === "area" && <polygon points={`${PAD},${HEIGHT-PAD} ${points} ${WIDTH-PAD},${HEIGHT-PAD}`} fill="var(--ds-primary)" opacity=".16" />}
        <polyline points={points} fill="none" stroke="var(--ds-primary)" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
      </>}
      {dates.map((record,index)=><text key={`${record.id}-${index}`} x={PAD+index*plotWidth/2} y={HEIGHT-10} textAnchor={index===0?"start":index===2?"end":"middle"} fill="currentColor" className="text-[10px] text-[var(--ds-text-muted)]">{new Date(record.recordedAt).toLocaleDateString(undefined,{month:"short",day:"numeric",hour:"2-digit",minute:"2-digit"})}</text>)}
    </svg>
    <figcaption className="sr-only">{description}</figcaption>
  </figure>;
}
