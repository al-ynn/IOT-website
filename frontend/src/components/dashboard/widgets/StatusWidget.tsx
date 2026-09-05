import { Badge } from "../../ui";

/*
  Status widget v3 — neon beacon:
  large glowing orb inside luminous ring, glow badge.
*/
export default function StatusWidget({
  status,
  label,
}: {
  status: "online" | "offline" | "warning" | "error";
  label?: string;
}) {
  const tones = {
    online: { badge: "success" as const, dot: "var(--ds-success)" },
    offline: { badge: "neutral" as const, dot: "var(--ds-text-subtle)" },
    warning: { badge: "warning" as const, dot: "var(--ds-accent)" },
    error: { badge: "danger" as const, dot: "var(--ds-danger)" },
  }[status];
  return (
    <div className="flex h-full flex-col items-center justify-center gap-3">
      <span
        aria-hidden
        className={`relative grid h-14 w-14 place-items-center rounded-full ${status === "online" ? "pulse-dot" : ""}`}
        style={{
          background: "var(--ds-bg)",
          boxShadow: `0 0 0 1px var(--ds-border-luminous), 0 0 24px -4px color-mix(in oklab, ${tones.dot} 70%, transparent), inset 0 2px 6px rgb(0 0 0 / .5)`,
        }}
      >
        <span
          className="h-5 w-5 rounded-full"
          style={{
            background: tones.dot,
            boxShadow: status === "offline" ? "none" : `0 0 14px 3px color-mix(in oklab, ${tones.dot} 75%, transparent)`,
          }}
        />
      </span>
      <span
        className="text-[10px] font-bold uppercase tracking-[0.24em]"
        style={{ color: tones.dot, textShadow: status === "offline" ? "none" : `0 0 10px color-mix(in oklab, ${tones.dot} 60%, transparent)` }}
      >
        {label ?? status}
      </span>
      <Badge tone={tones.badge} className="hidden">{label ?? status}</Badge>
    </div>
  );
}
