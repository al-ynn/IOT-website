import { useEffect, useState } from "react";
import { pullUpdateService } from "../../services/pull-update.service";
import type { ResourceRevisionState, RevisionRangeSummary, RevisionReminderState } from "../../types/pull-update";
import ReminderChoices from "./ReminderChoices";
import RevisionComparison from "./RevisionComparison";

export default function UpdateAvailablePanel({resourceId,state,onState,hasDraft=false}:{resourceId:string;state:ResourceRevisionState;onState:(state:ResourceRevisionState)=>void;hasDraft?:boolean}){
  const [review,setReview]=useState<RevisionRangeSummary|null>(null);
  const [reminder,setReminder]=useState<RevisionReminderState|null>(null);
  const [busy,setBusy]=useState(false);
  useEffect(()=>{void pullUpdateService.reminder("device",resourceId).then(setReminder)},[resourceId]);
  if(!state.updateAvailable)return <p className="text-xs text-[var(--ds-text-muted)]">Revision {state.acceptedRevision.revisionNumber} · Current</p>;
  const pull=async()=>{setBusy(true);try{onState(await pullUpdateService.pull("device",resourceId));setReview(null);setReminder(null)}finally{setBusy(false)}};
  return <aside aria-label="Update available" className="rounded-md border border-[var(--ds-border)] p-3">
    <p className="font-medium">Update Available · Revision {state.latestRevision.revisionNumber}</p>
    <p className="text-sm text-[var(--ds-text-muted)]">Viewing Revision {state.acceptedRevision.revisionNumber}; live Device state remains current.</p>
    {reminder?.isSnoozed&&<p role="status" className="text-xs">Reminder set for {new Date(reminder.remindAt!).toLocaleString()}.</p>}
    {reminder?.status==="none"&&<p role="status" className="text-xs">No automatic reminder set. Update remains available.</p>}
    {hasDraft&&<p role="status" className="mt-2 text-sm">Your saved Draft is protected. Apply or discard it before Pulling.</p>}
    <div className="mt-2 flex flex-wrap gap-2"><button type="button" onClick={()=>pullUpdateService.review("device",resourceId).then(setReview)} className="rounded border px-3 py-1 text-sm">Review Changes</button><button type="button" disabled={busy||hasDraft} onClick={()=>void pull()} className="rounded border px-3 py-1 text-sm">Pull Update</button><ReminderChoices resourceId={resourceId} onDone={setReminder}/></div>
    {review&&<div role="dialog" aria-label="Review changes" className="mt-3 max-h-[70vh] overflow-auto rounded border p-3"><RevisionComparison comparison={review}/><button type="button" className="mt-4 rounded border px-3 py-1" onClick={()=>setReview(null)}>Close</button></div>}
  </aside>;
}
