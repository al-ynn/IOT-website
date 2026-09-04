import {Link} from "react-router-dom";
import {Button,Card,CardContent,PageTitle} from "../ui";

export default function ModuleUnavailable({title,description,futurePhase,backTo="/app/dashboard"}:{title:string;description:string;futurePhase?:string;backTo?:string}){
  return <div className="mx-auto max-w-3xl">
    <Card>
      <CardContent className="space-y-4 p-6">
        <div>
          <p className="text-[10px] uppercase tracking-wide text-[var(--ds-text-subtle)]">{futurePhase??"Future module"}</p>
          <PageTitle className="mt-1 text-2xl">{title}</PageTitle>
        </div>
        <p className="text-sm text-[var(--ds-text-muted)]">{description}</p>
        <Link to={backTo}><Button>Back to dashboard</Button></Link>
      </CardContent>
    </Card>
  </div>;
}
