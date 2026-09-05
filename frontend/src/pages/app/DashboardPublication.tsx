import {useCallback,useEffect,useState} from "react";
import {Link,useParams} from "react-router-dom";
import {Button,Card,CardContent,ErrorState,LoadingState,PageTitle} from "../../components/ui";
import {useAuth} from "../../hooks/useAuth";
import {useFeedback} from "../../context/FeedbackContext";
import {getDashboardPublicationState,submitDashboardPublication,type DashboardPublicationState} from "../../services/dashboard-publication.service";

export default function DashboardPublication(){
 const{id=""}=useParams(),{user}=useAuth(),feedback=useFeedback(),admin=user?.platformRole==="platform_admin";
 const[state,setState]=useState<DashboardPublicationState|null>(null),[loading,setLoading]=useState(true),[error,setError]=useState("");
 const load=useCallback(()=>getDashboardPublicationState(id).then(setState).catch(()=>setError("Publication state could not be loaded.")).finally(()=>setLoading(false)),[id]);
 useEffect(()=>{void load()},[load]);
 async function submit(){if(!state?.latestRevision)return;setError("");try{await submitDashboardPublication(id,state.latestRevision.id);feedback.notify({tone:"success",title:admin?"Dashboard published":"Dashboard submitted",description:admin?"The revision is live. Other active Admins in this organization were notified.":"An Organization Admin can now review this pinned revision."});await load()}catch{setError("This revision could not be submitted. Check edit access, sources, and active review state.")}}
 if(loading)return <LoadingState label="Loading publication state..."/>;
 if(error&&!state)return <ErrorState description={error}/>;
 return <div className="mx-auto max-w-3xl space-y-5"><Link to={`/app/dashboard/${id}`} className="text-sm">← Dashboard</Link><PageTitle>Dashboard Publication</PageTitle><p className="text-sm text-[var(--ds-text-muted)]">{admin?"Organization Admin publications become live immediately. Other Admins are notified for awareness.":"Submission pins one immutable revision for Organization Admin review. Later private edits are not published automatically."}</p>{error&&<p role="alert" className="text-sm text-[var(--ds-danger)]">{error}</p>}<Card><CardContent className="space-y-3"><p>Latest private revision: {state?.latestRevision?.number??"Unavailable"}</p><p>Current published version: {state?.currentPublicationVersion?.number??"Not published"}</p>{state?.hasPrivateChanges&&<p className="text-sm font-medium">Newer private changes are not published.</p>}{state?.activeSubmission?<p className="text-sm">Revision {state.activeSubmission.submittedRevision.number} is currently under review.</p>:<Button disabled={!state?.latestRevision} onClick={()=>void submit()}>{admin?"Publish latest revision":"Submit latest revision for Admin review"}</Button>}</CardContent></Card></div>;
}
