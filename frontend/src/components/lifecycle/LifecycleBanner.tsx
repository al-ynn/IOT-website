/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useState } from "react";
import { Archive, PowerOff, RotateCcw } from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import {
  archiveResource,
  disableResource,
  getLifecycle,
  restoreResource,
} from "../../services/lifecycle.service";
import type { ResourceLifecycle } from "../../types/lifecycle";
import { Badge, Button } from "../ui";

export default function LifecycleBanner({
  type,
  id,
  admin = false,
}: {
  type: "device" | "device_template";
  id: string | number;
  admin?: boolean;
}) {
  const { user } = useAuth();
  const isAdmin = admin || user?.platformRole === "platform_admin";
  const [state, setState] = useState<ResourceLifecycle | null>(null);
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState("");
  const load = useCallback(
    async () => setState(await getLifecycle(type, String(id))),
    [type, id],
  );
  useEffect(() => {
    void load();
  }, [load]);
  const act = async (action: "disable" | "restore" | "archive") => {
    if (!state || pending) return;
    const label = action[0].toUpperCase() + action.slice(1);
    const detail =
      action === "restore"
        ? "The lifecycle becomes Active. Current permissions and runtime activation remain unchanged; stale queued work is not resumed."
        : "Configuration, history, access, and related resources are retained.";
    if (!confirm(`${label} this resource? ${detail}`)) return;
    setPending(true);
    setMessage("");
    try {
      const next =
        action === "disable"
          ? await disableResource(type, String(id), state)
          : action === "restore"
            ? await restoreResource(type, String(id), state)
            : await archiveResource(type, String(id), state);
      setState(next);
      setMessage(
        next.changed
          ? `${label} completed.`
          : "Resource was already in the requested lifecycle state.",
      );
    } catch {
      try {
        setState(await getLifecycle(type, String(id)));
        setMessage(
          "The request outcome was uncertain, so the current lifecycle was refreshed.",
        );
      } catch {
        setMessage(
          "Lifecycle could not be refreshed. Retry the same absolute action.",
        );
      }
    } finally {
      setPending(false);
    }
  };
  if (!state || (state.state === "active" && !isAdmin)) return null;
  return (
    <section
      aria-live="polite"
      className={`rounded-lg border p-4 ${state.state === "active" ? "border-[var(--ds-border)]" : "border-orange-400/40 bg-orange-500/10"}`}
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <PowerOff size={17} aria-hidden />
            <strong className="text-sm">Resource lifecycle</strong>
            <Badge>{state.label}</Badge>
          </div>
          <p className="mt-1 text-xs text-[var(--ds-text-muted)]">
            {state.state === "active"
              ? "Available for normal authorized use."
              : state.state === "disabled"
                ? "Retained and viewable, but normal editing, sharing, review, publication, and new use are blocked."
                : "Retained for historical reference; normal collaborative and operational use is blocked."}
          </p>
          {message && <p className="mt-1 text-xs">{message}</p>}
        </div>
        {isAdmin && (
          <div className="flex flex-wrap gap-2">
            {state.capabilities.canDisable && (
              <Button
                size="small"
                variant="danger"
                leadingIcon={<PowerOff size={14} />}
                disabled={pending}
                loading={pending}
                onClick={() => void act("disable")}
              >
                Disable
              </Button>
            )}
            {state.capabilities.canArchive && (
              <Button
                size="small"
                variant="outline"
                leadingIcon={<Archive size={14} />}
                disabled={pending}
                onClick={() => void act("archive")}
              >
                Archive
              </Button>
            )}
            {state.capabilities.canRestore && (
              <Button
                size="small"
                leadingIcon={<RotateCcw size={14} />}
                disabled={pending}
                loading={pending}
                onClick={() => void act("restore")}
              >
                Restore
              </Button>
            )}
          </div>
        )}
      </div>
    </section>
  );
}
