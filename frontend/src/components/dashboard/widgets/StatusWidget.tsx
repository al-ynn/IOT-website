import { Badge } from "../../ui";

/*
  Status widget v2 — luminous state beacon (ref 8):
  large glowing status orb inside recessed panel + tone badge.
*/
export default function StatusWidget({
  status,
  label,
}: {
  status: "online" | "offline" | "warning" | "error";
  label?: string;
}) {
  const tones = {
    online: { badge: "success" as const, dot: "var(--ds-success)", glow: "var(--ds-success)" },
    offline: { badge: "neutral" as const, dot: "var(--ds-text-subtle)", glow: "transparent" },
    warning: { badge: "warning" as const, dot: "var(--ds-accent)", glow: "var(--ds-accent)" },
    error: { badge: "danger" as const, dot: "var(--ds-danger)", glow: "var(--ds-danger)" },
  }[status];
  return (
    <div className="flex h-full flex-col items-center justify-center gap-2.5">
      <span
        aria-hidden
        className={`grid h-12 w-12 place-items-center rounded-full ${status === "online" ? "pulse-dot" : ""}`}
        style={{
          background: "var(--ds-card-alt)",
          boxShadow:
            "inset 0 1px 2px var(--ds-inset-shadow), inset 0 0 0 1px var(--ds-border-subtle)",
        }}
      >
        <span
          className="h-4 w-4 rounded-full"
          style={{
            background: tones.dot,
            boxShadow: status === "offline" ? "none" : `0 0 12px 2px color-mix(in oklab, ${tones.glow} 60%, transparent)`,
          }}
        />
      </span>
      <Badge tone={tones.badge}>{label ?? status}</Badge>
    </div>
  );
}
