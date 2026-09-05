import { useCallback, useEffect, useState } from "react";
import DashboardWorkspace from "../dashboard/DashboardWorkspace";
import { getDeviceDashboard, updateDeviceDashboard } from "../../services/dashboard.service";
import { applyDraft, discardDraft, getDraft, saveDraft } from "../../services/draft.service";
import { pullUpdateService } from "../../services/pull-update.service";
import type { Dashboard } from "../../types/dashboard";
import type { RevisionConflict, ResourceDraft } from "../../types/draft";
import type { ResourceRevisionState, RevisionRangeSummary } from "../../types/pull-update";
import UpdateAvailablePanel from "../collaboration/UpdateAvailablePanel";
import RevisionComparison from "../collaboration/RevisionComparison";
import { Modal } from "../ui";

export default function DeviceDashboardPanel({ deviceId, admin = false, canEdit = false }: { deviceId: string; admin?: boolean; canEdit?: boolean }) {
  const [state, setState] = useState<ResourceRevisionState | null>(null);
  const [refresh, setRefresh] = useState(0);
  const [conflict, setConflict] = useState<RevisionConflict | null>(null);
  const [working, setWorking] = useState<Dashboard | null>(null);
  const [draft, setDraft] = useState<ResourceDraft | null>(null);
  const [review, setReview] = useState<RevisionRangeSummary | null>(null);
  const load = useCallback(async () => {
    void refresh;
    const data = await getDeviceDashboard(deviceId, admin);
    setState(data.revisionState ?? null);
    return data;
  }, [deviceId, admin, refresh]);
  useEffect(() => {
    if (!admin && canEdit) void getDraft("device", deviceId).then(setDraft);
  }, [admin, canEdit, deviceId, refresh]);
  const save = useCallback((dashboard: Dashboard) => updateDeviceDashboard(deviceId, {
    ...dashboard,
    // The Dashboard payload records the revision actually loaded into this editor.
    // Using live revision-state metadata here can silently move a stale editor's
    // base forward after another collaborator saves.
    baseRevisionId: dashboard.baseRevisionId ?? state?.acceptedRevision.id,
  }, admin), [deviceId, admin, state?.acceptedRevision.id]);
  const onConflict = (error: unknown, dashboard: Dashboard) => {
    setWorking(dashboard);
    setConflict((error as { response?: { data?: RevisionConflict } }).response?.data ?? null);
  };
  const snapshot = () => ({ metadata: {}, dashboard: working ? { name: working.name, description: working.description ?? null, widgets: working.widgets.map((widget) => ({ id: widget.id, type: widget.type, title: widget.settings.title, layout: widget.layout, configuration: { deviceId: widget.settings.datasource?.deviceId, telemetryKey: widget.settings.datasource?.telemetryKey, unit: widget.settings.datasource?.unit, timeRange: widget.settings.timeRange, chartType: widget.settings.chartType, minimum: widget.settings.minimum, maximum: widget.settings.maximum } })) } : null, parameters: [] });
  const keep = async () => {
    if (!conflict) return;
    setDraft(await saveDraft("device", deviceId, conflict.submittedBaseRevision.id, snapshot()));
    setConflict(null);
  };
  const discardAndPull = async () => {
    if (!confirm(`Discard your unsaved changes and saved draft, then update to Revision ${conflict?.latestRevision.revisionNumber}?`)) return;
    await discardDraft("device", deviceId);
    const next = await pullUpdateService.pull("device", deviceId);
    setState(next); setDraft(null); setConflict(null); setWorking(null); setRefresh((value) => value + 1);
  };
  const actOnDraft = async (action: "apply" | "discard") => {
    if (action === "discard" && !confirm("Discard your saved Draft? The canonical Dashboard is unchanged.")) return;
    if (action === "apply") await applyDraft("device", deviceId); else await discardDraft("device", deviceId);
    setDraft(null); setRefresh((value) => value + 1);
  };
  return <div className="space-y-3">
    {draft && <section aria-label="My Draft" className="rounded border p-2 text-xs"><p role="status"><strong>My Draft</strong> · based on Revision {draft.baseRevisionNumber}{draft.isBehind ? " · Newer shared changes available" : ""}</p><div className="mt-2 flex gap-2"><button type="button" className="rounded border px-3 py-1" onClick={() => void actOnDraft("apply")}>Apply Draft</button><button type="button" className="rounded border px-3 py-1" onClick={() => void actOnDraft("discard")}>Discard Draft</button></div></section>}
    {!admin && state && <UpdateAvailablePanel resourceId={deviceId} state={state} onState={(next) => { setState(next); if (!next.updateAvailable) setRefresh((value) => value + 1); }} hasDraft={Boolean(draft)} />}
    <DashboardWorkspace load={load} save={save} canEdit={canEdit && !(state?.updateAvailable)} deviceId={deviceId} onConflict={onConflict} />
    <Modal
      open={Boolean(conflict)}
      onClose={() => setConflict(null)}
      title="Newer changes are available"
      description={conflict ? `You started editing Revision ${conflict.submittedBaseRevision.revisionNumber}. Latest shared revision is ${conflict.latestRevision.revisionNumber}${conflict.latestContributor ? ` by ${conflict.latestContributor.name}` : ""}.` : undefined}
      footer={<><button type="button" onClick={() => setConflict(null)} className="rounded border px-3 py-2">Cancel</button><button type="button" onClick={() => void keep()} className="rounded border px-3 py-2">Save My Draft</button><button type="button" onClick={() => void discardAndPull()} className="rounded border border-[var(--ds-danger)] px-3 py-2">Discard My Changes &amp; Pull</button></>}
    >
      <button type="button" onClick={() => pullUpdateService.review("device", deviceId).then(setReview)} className="rounded border px-3 py-2">Review Incoming Changes</button>
      {review && <div className="mt-4"><RevisionComparison comparison={review} /><button type="button" onClick={() => setReview(null)}>Close review</button></div>}
    </Modal>
  </div>;
}
