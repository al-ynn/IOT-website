import {Activity,BarChart3,BellRing,Building2,CloudCog,Cpu,GitBranch,LockKeyhole,Radio,Workflow} from "lucide-react";
import {FeatureCards,PublicHero,PublicSection} from "../components/marketing/PublicSections";

export default function Home(){return <>
 <PublicHero eyebrow="IoT operations platform" title="Turn connected-device data into reliable operations." description="Manage devices, stream telemetry, build analytics, and automate responses from one secure workspace."/>
 <PublicSection title="One platform for the complete IoT lifecycle" description="Move from first connection to production operations without stitching together separate tools."><FeatureCards items={[
  {title:"Devices",description:"Provision, organize, and monitor connected assets.",icon:Cpu},
  {title:"Telemetry",description:"Ingest and inspect real-time device signals.",icon:Radio},
  {title:"Analytics",description:"Find patterns and track operational performance.",icon:BarChart3},
  {title:"Automation",description:"Trigger reliable actions from conditions and events.",icon:Workflow},
 ]}/></PublicSection>
 <PublicSection title="A clear path from signal to action"><div className="grid gap-3 md:grid-cols-4">{[{title:"Device",icon:Cpu},{title:"Telemetry",icon:Activity},{title:"Analytics",icon:BarChart3},{title:"Automation",icon:GitBranch}].map((step,index)=>{const Icon=step.icon;return <div key={step.title} className="flex items-center gap-3 rounded-[10px] border border-[var(--ds-border-subtle)] bg-[var(--ds-card)] p-4"><span className="text-xs text-[var(--ds-text-subtle)]">0{index+1}</span><Icon size={18} className="text-[var(--ds-primary)]"/><span className="text-sm font-medium">{step.title}</span></div>})}</div></PublicSection>
 <PublicSection title="Built for serious deployments" description="A focused operator experience backed by controls for growing organizations."><FeatureCards items={[
  {title:"Scalable operations",description:"Coordinate fleets and teams as deployments grow.",icon:CloudCog},
  {title:"Security controls",description:"Keep access scoped through roles and organization boundaries.",icon:LockKeyhole},
  {title:"Flexible integrations",description:"Connect existing services through APIs and event workflows.",icon:BellRing},
  {title:"Organization ready",description:"Give teams a shared, governed operational workspace.",icon:Building2},
 ]}/></PublicSection>
 <PublicHero eyebrow="Start building" title="Connect your first device today." description="Create a workspace and bring telemetry, dashboards, analytics, and automation together." primary={{label:"Create account",to:"/register"}} secondary={{label:"View pricing",to:"/pricing"}}/>
 </>}
