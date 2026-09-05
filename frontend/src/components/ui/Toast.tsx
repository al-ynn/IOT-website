import type { ReactNode } from "react";
import { AlertCircle, AlertTriangle, CheckCircle2, Info, X } from "lucide-react";
import { cn } from "../../utils/cn";

export type ToastTone = "info" | "success" | "warning" | "error";

const toneRing: Record<ToastTone, string> = {
  info: "text-[var(--ds-info)] bg-[var(--ds-info-surface)]",
  success: "text-[var(--ds-success)] bg-[var(--ds-success-surface)]",
  warning: "text-[var(--ds-accent)] bg-[var(--ds-accent-surface)]",
  error: "text-[var(--ds-danger)] bg-[var(--ds-danger-surface)]",
};

export function Toast({
  title,
  description,
  tone = "info",
  onDismiss,
  action,
}: {
  title: string;
  description?: string;
  tone?: ToastTone;
  onDismiss?: () => void;
  action?: ReactNode;
}) {
  const Icon =
    tone === "success"
      ? CheckCircle2
      : tone === "warning"
        ? AlertTriangle
        : tone === "error"
          ? AlertCircle
          : Info;
  return (
    <div
      role={tone === "error" ? "alert" : "status"}
      className="pointer-events-auto flex w-full max-w-sm gap-3 rounded-[10px] border border-[var(--ds-border-subtle)] bg-[var(--ds-surface-elevated)] p-3 shadow-[var(--ds-shadow-lg)]"
    >
      <span
        className={cn(
          "mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-[8px]",
          toneRing[tone],
        )}
      >
        <Icon size={16} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-[13px] font-semibold text-[var(--ds-text)]">{title}</p>
        {description && (
          <p className="mt-0.5 text-xs leading-5 text-[var(--ds-text-muted)]">{description}</p>
        )}
        {action && <div className="mt-2">{action}</div>}
      </div>
      {onDismiss && (
        <button
          aria-label="Dismiss notification"
          onClick={onDismiss}
          className="grid h-6 w-6 place-items-center rounded text-[var(--ds-text-subtle)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-text)]"
        >
          <X size={14} />
        </button>
      )}
    </div>
  );
}
