import {useEffect,useMemo,useRef,useState,type ReactNode} from "react";
import {useLocation} from "react-router-dom";
import AppHeader from "../components/layout/AppHeader";
import PrimarySidebar from "../components/layout/PrimarySidebar";
import SecondarySidebar from "../components/layout/SecondarySidebar";
import MobileSidebar from "../components/layout/MobileSidebar";
import {visiblePrimaryNavigationItems} from "../navigation/app-navigation";
import {resolveSecondaryNavigation} from "../navigation/secondary-navigation";
import {useAuth} from "../hooks/useAuth";
import {useOrganization} from "../hooks/useOrganization";
import usePermission from "../hooks/usePermission";
import useSidebarPreference from "../hooks/useSidebarPreference";

export default function AppLayout({children}:{children?:ReactNode}){
  const [mobilePrimaryOpen,setMobilePrimaryOpen]=useState(false);
  const [mobileSecondaryOpen,setMobileSecondaryOpen]=useState(false);
  const [primaryCollapsed,togglePrimary]=useSidebarPreference("primarySidebarCollapsed");
  const [secondaryCollapsed,toggleSecondary]=useSidebarPreference("secondarySidebarCollapsed");
  const {pathname}=useLocation();
  const previousPath=useRef(pathname);
  const {can}=usePermission();
  const {user}=useAuth();
  const {organization}=useOrganization();
  const isAdmin=user?.platformRole==="platform_admin";
  const primaryItems=useMemo(()=>visiblePrimaryNavigationItems(isAdmin,can),[isAdmin,can]);
  const secondary=useMemo(()=>resolveSecondaryNavigation(pathname,isAdmin,can),[pathname,isAdmin,can]);
  const padding=secondary?(primaryCollapsed?(secondaryCollapsed?"lg:pl-28":"lg:pl-[17rem]"):(secondaryCollapsed?"lg:pl-64":"lg:pl-[26rem]")):(primaryCollapsed?"lg:pl-16":"lg:pl-52");
  useEffect(()=>{
    const heading=document.querySelector<HTMLElement>("#main-content h1");
    document.title=`${heading?.textContent?.trim()||"IoT Platform"} — IoT Platform`;
    if(previousPath.current!==pathname){requestAnimationFrame(()=>document.getElementById("main-content")?.focus({preventScroll:true}));previousPath.current=pathname;}
  },[pathname]);
  return <div className="min-h-screen bg-[var(--ds-bg)] text-[var(--ds-text)]">
    <a href="#main-content" className="fixed left-3 top-3 z-[200] -translate-y-20 rounded bg-[var(--ds-primary-hover)] px-4 py-2 text-sm font-semibold text-white transition-transform focus:translate-y-0">Skip to main content</a>
    <div className={`fixed inset-y-0 left-0 z-40 hidden transition-[width] duration-200 lg:block ${primaryCollapsed?"w-16":"w-52"}`}><PrimarySidebar items={primaryItems} collapsed={primaryCollapsed} onToggle={togglePrimary} homePath="/app/dashboard"/></div>
    {secondary&&<div className={`fixed inset-y-0 z-[39] hidden transition-[left,width] duration-200 lg:block ${primaryCollapsed?"left-16":"left-52"} ${secondaryCollapsed?"w-12":"w-52"}`}><SecondarySidebar resolved={secondary} collapsed={secondaryCollapsed} onToggle={toggleSecondary}/></div>}
    <MobileSidebar open={mobilePrimaryOpen} onClose={()=>setMobilePrimaryOpen(false)} label="Primary navigation drawer"><PrimarySidebar items={primaryItems} onNavigate={()=>setMobilePrimaryOpen(false)} homePath="/app/dashboard"/></MobileSidebar>
    {secondary&&<MobileSidebar open={mobileSecondaryOpen} onClose={()=>setMobileSecondaryOpen(false)} label={`${secondary.context.label} navigation drawer`}><SecondarySidebar resolved={secondary} onNavigate={()=>setMobileSecondaryOpen(false)}/></MobileSidebar>}
    <div className={`transition-[padding] duration-200 ${padding}`}>
      <AppHeader onOpenNavigation={()=>{setMobileSecondaryOpen(false);setMobilePrimaryOpen(true)}} onOpenContextNavigation={()=>{setMobilePrimaryOpen(false);setMobileSecondaryOpen(true)}} hasContextNavigation={Boolean(secondary)} contextNavigationLabel={secondary?.context.label} context={organization?.name??(isAdmin?"Administration":"Operations")}/>
      <main id="main-content" tabIndex={-1} className="mx-auto max-w-[1600px] p-4 sm:p-5 lg:p-6">{children}</main>
    </div>
  </div>;
}
