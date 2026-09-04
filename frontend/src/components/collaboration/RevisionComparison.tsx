import type { RevisionDiffEntry, RevisionRangeSummary } from "../../types/pull-update";

const labels={added:"Added",removed:"Removed",changed:"Changed",moved:"Moved"} as const;
const value=(input:unknown)=>{
  if(input===null||input===undefined||input==="")return "Not set";
  if(typeof input==="object")return Object.entries(input as Record<string,unknown>).filter(([,v])=>v!==null&&v!==undefined&&v!=="").map(([k,v])=>`${k}: ${String(v)}`).join(" · ")||"Not set";
  return String(input);
};
function Entry({entry,focused}:{entry:RevisionDiffEntry;focused:boolean}){
  const changes=entry.metadata.changes??[];
  return <li id={`change-${entry.identity.replace(":","-")}`} className={`rounded border p-3 ${focused?"ring-2 ring-[var(--ds-primary)]":""}`}>
    <div className="flex flex-wrap items-center gap-2"><span className="rounded border px-2 py-0.5 text-xs font-semibold">{labels[entry.changeType]}</span><h4 className="font-medium">{entry.label}</h4></div>
    {changes.length>0?<dl className="mt-3 space-y-3">{changes.map((change,index)=><div key={`${change.field}-${index}`}><dt className="text-xs font-semibold">{change.field} · {labels[change.changeType]}</dt><dd className="mt-1 grid gap-2 sm:grid-cols-2"><div className="rounded bg-[var(--ds-surface-subtle)] p-2"><span className="block text-[10px] font-semibold uppercase">Before</span>{value(change.before)}</div><div className="rounded bg-[var(--ds-surface-subtle)] p-2"><span className="block text-[10px] font-semibold uppercase">After</span>{value(change.after)}</div></dd></div>)}</dl>:<div className="mt-3 grid gap-2 sm:grid-cols-2"><div className="rounded bg-[var(--ds-surface-subtle)] p-2"><span className="block text-[10px] font-semibold uppercase">Before</span>{value(entry.before)}</div><div className="rounded bg-[var(--ds-surface-subtle)] p-2"><span className="block text-[10px] font-semibold uppercase">After</span>{value(entry.after)}</div></div>}
  </li>;
}
export default function RevisionComparison({comparison}:{comparison:RevisionRangeSummary}){
  return <div className="space-y-4"><header><h3 className="text-lg font-semibold">Revision {comparison.fromRevision.revisionNumber} → {comparison.toRevision.revisionNumber}</h3><p className="text-sm text-[var(--ds-text-muted)]">{comparison.summary.changeCount} net changes across {comparison.summary.changedSections.length} sections.</p></header>
    {comparison.sections.length===0?<p role="status">No net configuration differences.</p>:comparison.sections.map(section=><section key={section.key} aria-labelledby={`diff-${section.key}`}><h3 id={`diff-${section.key}`} className="mb-2 font-semibold">{section.label} · {section.entries.length}</h3><ul className="space-y-2">{section.entries.map(entry=><Entry key={entry.identity} entry={entry} focused={comparison.focus===entry.identity}/>)}</ul></section>)}
    {comparison.summary.intermediateRevisions.length>0&&<details><summary className="cursor-pointer font-medium">Revision timeline and contributors</summary><ol className="mt-2 space-y-1 text-sm">{comparison.summary.intermediateRevisions.map(revision=><li key={revision.id}>Revision {revision.revisionNumber}: {revision.changeSummary} · {revision.createdBy?.name??"System"}</li>)}</ol></details>}
  </div>;
}
