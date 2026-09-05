import {ChevronRight} from "lucide-react";
import {Link,useLocation} from "react-router-dom";
import {navigationLabels} from "../../navigation/app-navigation";

const title=(value:string)=>navigationLabels[value]??(value.length>18?value.slice(0,8)+"…":value.replace(/-/g," ").replace(/\b\w/g,c=>c.toUpperCase()));

export default function Breadcrumbs(){
  const {pathname}=useLocation();
  const segments=pathname.split("/").filter(Boolean);
  if(!segments.length)return null;
  const visible=segments.slice(1);
  return <nav aria-label="Breadcrumb" className="min-w-0"><ol className="flex min-w-0 items-center gap-1 text-xs">{visible.map((segment,index)=>{const last=index===visible.length-1;const to="/"+[segments[0],...visible.slice(0,index+1)].join("/");return <li key={to} className="flex min-w-0 items-center gap-1">{index>0&&<ChevronRight size={12} className="shrink-0 text-[var(--ds-text-subtle)]"/>}{last?<span aria-current="page" className="truncate font-medium text-[var(--ds-text)]">{title(segment)}</span>:<Link to={to} className="truncate text-[var(--ds-text-muted)] hover:text-[var(--ds-text)]">{title(segment)}</Link>}</li>})}</ol></nav>;
}
