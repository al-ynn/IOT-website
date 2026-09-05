import {Menu,Moon,PanelLeft,Sun} from "lucide-react";
import Breadcrumbs from "./Breadcrumbs";
import SearchCommand from "./SearchCommand";
import UserMenu from "./UserMenu";
import {useTheme} from "../../design-system/themes/ThemeProvider";
import NotificationBell from "../notifications/NotificationBell";

/*
  App header — glass bar with luminous bottom edge (refs 8/9/10).
  Compact height preserved (h-14).
*/
export default function AppHeader({onOpenNavigation,onOpenContextNavigation,hasContextNavigation=false,contextNavigationLabel}:{onOpenNavigation():void;onOpenContextNavigation():void;hasContextNavigation?:boolean;contextNavigationLabel?:string}){
  const {theme,toggleTheme}=useTheme();
  return <header className="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-[var(--ds-border-subtle)] px-3 backdrop-blur-xl sm:px-5" style={{background:"color-mix(in oklab, var(--ds-bg) 82%, transparent)"}}>
    <div aria-hidden className="tech-strip absolute inset-x-0 bottom-0 h-px opacity-50"/>
    <div className="flex min-w-0 items-center gap-3">
      <button type="button" aria-label="Open navigation" onClick={onOpenNavigation} className="grid h-9 w-9 shrink-0 place-items-center rounded-[9px] border border-[var(--ds-border-subtle)] text-[var(--ds-text-muted)] hover:border-[var(--ds-primary-outline)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)] lg:hidden"><Menu size={18}/></button>
      {hasContextNavigation&&<button type="button" aria-label={`Open ${contextNavigationLabel??"contextual"} navigation`} onClick={onOpenContextNavigation} className="grid h-9 w-9 shrink-0 place-items-center rounded-[9px] border border-[var(--ds-border-subtle)] text-[var(--ds-text-muted)] hover:border-[var(--ds-primary-outline)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)] lg:hidden"><PanelLeft size={17} aria-hidden="true"/></button>}
      <div className="min-w-0">
        <Breadcrumbs/>
      </div>
    </div>
    <div className="flex items-center gap-1.5">
      <SearchCommand/>
      <NotificationBell/>
      <button type="button" aria-label={`Switch to ${theme==="dark"?"light":"dark"} theme`} onClick={toggleTheme} className="grid h-9 w-9 place-items-center rounded-[9px] border border-[var(--ds-border-subtle)] text-[var(--ds-text-muted)] hover:border-[var(--ds-primary-outline)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)]">{theme==="dark"?<Sun size={16}/>:<Moon size={16}/>}</button>
      <UserMenu/>
    </div>
  </header>;
}
