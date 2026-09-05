import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { BodyText, Button, Card, EmptyState, ErrorState, LoadingState } from "../ui";
import { listAdminCrashReports, listCrashReports } from "../../services/crash-report.service";
import type { CrashReport } from "../../types/crash-report";

export default function CrashReportsPanel({ deviceId, admin = false }: { deviceId: string; admin?: boolean }) {
  const [items, setItems] = useState<CrashReport[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const result = admin
        ? await listAdminCrashReports({ device_id: deviceId, per_page: 25 })
        : await listCrashReports({ device_id: deviceId, per_page: 25 });
      setItems(result.data.slice(0, 10));
    } catch {
      setError("Crash Reports could not be loaded.");
    } finally {
      setLoading(false);
    }
  }, [admin, deviceId]);
  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);
  if (loading) return <LoadingState label="Loading Crash Reports..." />;
  if (error) return <ErrorState description={error} retry={load} />;
  return <Card className="p-5">
    <div className="mb-4 flex items-center justify-between">
      <div><h2 className="font-semibold">Crash Reports</h2><BodyText className="mt-1">Recent diagnostics submitted by this Device.</BodyText></div>
      {!admin && <Link to={`/app/debug/crashes?device_id=${deviceId}`}><Button variant="outline">View all</Button></Link>}
    </div>
    {items.length === 0 ? <EmptyState title="No crash reports received" description="No authenticated Device crash diagnostics have been submitted." /> :
      <div className="overflow-x-auto"><table className="w-full min-w-[650px] text-left text-xs"><thead><tr><th className="p-3">Received</th><th className="p-3">Crash type</th><th className="p-3">Reason</th><th className="p-3">Firmware</th><th className="p-3">Action</th></tr></thead><tbody>{items.map(item => <tr key={item.id} className="border-t border-[var(--ds-border-subtle)]"><td className="p-3">{new Date(item.receivedAt).toLocaleString()}</td><td className="p-3 font-mono">{item.crashType}</td><td className="max-w-xs truncate p-3">{item.reason ?? "—"}</td><td className="p-3">{item.firmwareVersion ?? "—"}</td><td className="p-3">{admin ? "Available in global API" : <Link className="text-[var(--ds-primary)] hover:underline" to={`/app/debug/crashes/${item.id}`}>Details</Link>}</td></tr>)}</tbody></table></div>}
  </Card>;
}
