import {ChevronLeft,ChevronRight} from "lucide-react";
import {Link} from "react-router-dom";
import type {ResolvedSecondaryNavigation} from "../../navigation/secondary-navigation";

/*
  Secondary/contextual navigation — denser technical panel:
  luminous area badge, grouped resources with hairline separators,
  glowing selected state. Functional structure preserved.
*/
export default function SecondarySidebar({resolved,collapsed=false,onToggle,onNavigate}:{resolved:ResolvedSecondaryNavigation;collapsed?:boolean;onToggle?:()=>void;onNavigate?:()=>void}){
  const {context,sections}=resolved;
  const activeItemId=typeof window!=="undefined"?sections.flatMap(s=>s.items).find(i=>window.location.pathname.startsWith(i.path))?.id:undefined;
  if(collapsed)return <aside aria-label={`${context.label} contextual navigation`} className="flex h-full flex-col items-center border-r border-[var(--ds-border-subtle)] bg-[var(--ds-card)] py-3">
    <button type="button" aria-label={`Expand ${context.label} contextual navigation`} aria-expanded="false" onClick={onToggle} title={`Open ${context.label} navigation`} className="grid min-h-10 min-w-10 place-items-center rounded-[9px] border border-[var(--ds-border-subtle)] text-[var(--ds-text-muted)] hover:border-[var(--ds-primary-outline)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)] focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)]"><ChevronRight size={15} aria-hidden="true"/></button>
  </aside>;
  return <aside aria-label={`${context.label} contextual navigation`} className="relative flex h-full flex-col border-r border-[var(--ds-border-subtle)] bg-[var(--ds-card)]">
    <div aria-hidden className="pointer-events-none absolute inset-x-0 top-0 h-28" style={{background:"radial-gradient(180px 90px at 50% -30%, var(--ds-ambient-2), transparent 70%)"}}/>
    <div className="relative flex h-14 items-center gap-2 border-b border-[var(--ds-border-subtle)] px-3">
      <div className="min-w-0 flex-1">
        <p className="text-[9px] font-semibold uppercase tracking-[0.2em] text-[var(--ds-primary)]">Current area</p>
        <p className="truncate text-[13px] font-semibold text-[var(--ds-text)]">{context.label}</p>
      </div>
      {onToggle&&<button type="button" aria-label={`Collapse ${context.label} contextual navigation`} aria-expanded="true" onClick={onToggle} title={`Collapse ${context.label} navigation`} className="grid h-8 w-8 shrink-0 place-items-center rounded-[8px] border border-[var(--ds-border-subtle)] text-[var(--ds-text-muted)] hover:border-[var(--ds-primary-outline)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)] focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)]"><ChevronLeft size={14} aria-hidden="true"/></button>}
    </div>
    <nav aria-label={`${context.label} contextual pages`} className="relative flex-1 space-y-5 overflow-y-auto p-3">
      {sections.map(section=><section key={section.id} aria-labelledby={`secondary-${context.key}-${section.id}`}>
        <h2 id={`secondary-${context.key}-${section.id}`} className="mb-1.5 flex items-center gap-2 px-2 text-[9.5px] font-semibold uppercase tracking-[0.18em] text-[var(--ds-text-subtle)]">{section.label}<span aria-hidden className="h-px flex-1 bg-gradient-to-r from-[var(--ds-border)] to-transparent"/></h2>
        <div className="space-y-0.5">{section.items.map(entry=>{const Icon=entry.icon;const active=activeItemId===entry.id;return <Link key={entry.id} to={entry.path} onClick={onNavigate} aria-current={active?"page":undefined} className={`relative flex min-h-9 items-center gap-2 rounded-[8px] px-2.5 text-xs focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)] ${active?"text-[var(--ds-primary-soft)]":"text-[var(--ds-text-muted)] hover:text-[var(--ds-text)]"}`} style={active?{background:"linear-gradient(90deg, color-mix(in oklab, var(--ds-primary) 20%, transparent), color-mix(in oklab, var(--ds-primary) 5%, transparent))",boxShadow:"inset 0 0 0 1px var(--ds-primary-outline)"}:undefined}>
          {active&&<span aria-hidden className="absolute left-0 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-r-full" style={{background:"linear-gradient(180deg, var(--ds-primary-soft), var(--ds-primary))",boxShadow:"0 0 8px 1px var(--ds-primary-glow)"}}/>}
          <Icon size={14} aria-hidden="true" className={`shrink-0 ${active?"text-[var(--ds-primary-soft)]":"text-[var(--ds-text-subtle)]"}`}/>
          <span className="flex-1 truncate">{entry.label}</span>
        </Link>})}</div>
      </section>)}
    </nav>
  </aside>;
}
