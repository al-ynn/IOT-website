import { Battery, Cpu, MapPin } from "lucide-react";
import type { Device } from "../../../types/device";

/*
  Device widget v2 — compact device identity card (ref 5):
  gradient type icon chip, name/type, status pill, telemetry footer rows.
*/
export default function DeviceWidget({ device }: { device: Device }) {
  const status =
    device.status === "online" ? "online" : device.status === "maintenance" ? "warning" : "offline";
  const dot =
    status === "online"
      ? "var(--ds-success)"
      : status === "warning"
        ? "var(--ds-accent)"
        : "var(--ds-text-subtle)";
  return (
    <div className="flex h-full flex-col justify-between gap-2.5">
      <div className="flex items-start gap-2.5">
        <span
          aria-hidden
          className="grid h-9 w-9 shrink-0 place-items-center rounded-[9px] text-white"
          style={{
            background: "linear-gradient(135deg, var(--ds-primary), var(--ds-primary-strong))",
            boxShadow: "0 3px 10px -3px var(--ds-primary-glow), 0 1px 0 rgb(255 255 255 / .15) inset",
          }}
        >
          <Cpu size={16} />
        </span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-[var(--ds-text)]">{device.name}</p>
          <p className="text-[10px] font-medium uppercase tracking-[0.12em] text-[var(--ds-text-subtle)]">
            {device.type}
          </p>
        </div>
        <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-[var(--ds-border-subtle)] bg-[var(--ds-card-alt)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-[var(--ds-text-muted)]">
          <span
            aria-hidden
            className="h-1.5 w-1.5 rounded-full"
            style={{ background: dot, boxShadow: status === "offline" ? "none" : `0 0 6px 1px ${dot}` }}
          />
          {device.status}
        </span>
      </div>
      <div className="space-y-1 rounded-[8px] border border-[var(--ds-border-subtle)] bg-[var(--ds-card-alt)] p-2">
        {device.location && (
          <p className="flex items-center gap-2 text-[11px] text-[var(--ds-text-muted)]">
            <MapPin size={12} className="shrink-0 text-[var(--ds-primary)]" />
            <span className="truncate">{device.location.name}</span>
          </p>
        )}
        {device.battery !== undefined && (
          <p className="flex items-center gap-2 text-[11px] text-[var(--ds-text-muted)] tabular-nums" data-numeric>
            <Battery size={12} className="shrink-0 text-[var(--ds-primary)]" />
            {device.battery}%
            <span className="relative ml-1 h-1 flex-1 overflow-hidden rounded-full bg-[var(--ds-border)]">
              <span
                className="absolute inset-y-0 left-0 rounded-full"
                style={{
                  width: `${device.battery}%`,
                  background: "linear-gradient(90deg, var(--ds-primary), var(--ds-primary-soft))",
                  boxShadow: "0 0 6px 0 var(--ds-primary-glow)",
                }}
              />
            </span>
          </p>
        )}
      </div>
    </div>
  );
}
