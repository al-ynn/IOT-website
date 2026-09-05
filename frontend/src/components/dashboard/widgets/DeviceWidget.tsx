import { Battery, MapPin } from "lucide-react";
import { StatusIndicator } from "../../ui";
import type { Device } from "../../../types/device";

export default function DeviceWidget({ device }: { device: Device }) {
  const status =
    device.status === "online" ? "online" : device.status === "maintenance" ? "warning" : "offline";
  return (
    <div className="flex h-full flex-col justify-between gap-3">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-[var(--ds-text)]">{device.name}</p>
          <p className="mt-0.5 text-[11px] uppercase tracking-[.06em] text-[var(--ds-text-subtle)]">
            {device.type}
          </p>
        </div>
        <StatusIndicator status={status} />
      </div>
      <div className="space-y-1.5">
        {device.location && (
          <p className="flex items-center gap-2 text-xs text-[var(--ds-text-muted)]">
            <MapPin size={13} className="text-[var(--ds-primary)]" />
            <span className="truncate">{device.location.name}</span>
          </p>
        )}
        {device.battery !== undefined && (
          <p className="flex items-center gap-2 text-xs text-[var(--ds-text-muted)] tabular-nums" data-numeric>
            <Battery size={13} className="text-[var(--ds-primary)]" />
            {device.battery}%
          </p>
        )}
      </div>
    </div>
  );
}
