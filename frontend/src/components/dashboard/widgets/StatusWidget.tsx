import { StatusIndicator, Badge } from "../../ui";

/*
  Status widget — status dot + label; adds tone-appropriate badge for scannability.
*/
export default function StatusWidget({
  status,
  label,
}: {
  status: "online" | "offline" | "warning" | "error";
  label?: string;
}) {
  const toneMap: Record<typeof status, "success" | "neutral" | "warning" | "danger"> = {
    online: "success",
    offline: "neutral",
    warning: "warning",
    error: "danger",
  };
  return (
    <div className="flex h-full flex-col items-start justify-center gap-2">
      <StatusIndicator status={status} label={label} />
      <Badge tone={toneMap[status]}>{label ?? status}</Badge>
    </div>
  );
}
