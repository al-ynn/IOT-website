import {Menu,Moon,PanelLeft,Sun} from "lucide-react";
import Breadcrumbs from "./Breadcrumbs";
import SearchCommand from "./SearchCommand";
import UserMenu from "./UserMenu";
import {useTheme} from "../../design-system/themes/ThemeProvider";
import NotificationBell from "../notifications/NotificationBell";

export default function AppHeader({onOpenNavigation,onOpenContextNavigation,hasContextNavigation=false,contextNavigationLabel,context}:{onOpenNavigation():void;onOpenContextNavigation():void;hasContextNavigation?:boolean;contextNavigationLabel?:string;context?:string}){
  const {theme,toggleTheme}=useTheme();
  return <header className="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-[var(--ds-border-subtle)] bg-[color:var(--ds-bg)]/92 px-3 backdrop-blur sm:px-5">
    <div className="flex min-w-0 items-center gap-3">
      <button type="button" aria-label="Open navigation" onClick={onOpenNavigation} className="grid h-9 w-9 shrink-0 place-items-center rounded-[8px] text-[var(--ds-text-muted)] hover:bg-white/[.05] lg:hidden"><Menu size={19}/></button>
      {hasContextNavigation&&<button type="button" aria-label={`Open ${contextNavigationLabel??"contextual"} navigation`} onClick={onOpenContextNavigation} className="grid h-10 w-10 shrink-0 place-items-center rounded-[8px] text-[var(--ds-text-muted)] hover:bg-white/[.05] lg:hidden"><PanelLeft size={18} aria-hidden="true"/></button>}
      <div className="min-w-0">
        <Breadcrumbs/>
        {context&&<p className="mt-0.5 hidden truncate text-[10px] text-[var(--ds-text-subtle)] md:block">{context}</p>}
      </div>
    </div>
    <div className="flex items-center gap-1">
      <SearchCommand/>
      <NotificationBell/>
      <button type="button" aria-label={`Switch to ${theme==="dark"?"light":"dark"} theme`} onClick={toggleTheme} className="grid h-9 w-9 place-items-center rounded-[8px] text-[var(--ds-text-muted)] hover:bg-white/[.05] hover:text-[var(--ds-text)]">{theme==="dark"?<Sun size={17}/>:<Moon size={17}/>}</button>
      <UserMenu/>
    </div>
  </header>;
}
