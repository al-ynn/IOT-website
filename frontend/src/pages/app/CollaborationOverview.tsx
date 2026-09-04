import { useEffect, useState, type ReactNode } from "react";
import { Link, useNavigate } from "react-router-dom";
import { ActivityTimeline } from "../../components/notifications/activity";
import { Badge, Button, Card, CardContent, EmptyState, ErrorState, Input, LoadingState, PageTitle } from "../../components/ui";
import { getCollaborationOverview } from "../../services/collaboration-overview.service";
import type { CollaborationOverview as Overview, OverviewSection } from "../../types/collaboration-overview";

function Summary<T>({ section, empty, render, countLabel }: { section: OverviewSection<T>; empty: string; render: (item: T) => ReactNode; countLabel?: ReactNode }) {
  return <Card><CardContent className="space-y-4"><header className="flex items-start justify-between gap-3"><div><h2 className="font-semibold">{section.title}</h2><p className="text-sm text-[var(--ds-text-muted)]">{countLabel ?? <>{section.count} {section.count === 1 ? "resource" : "resources"}</>}</p></div><Button size="compact" variant="outline" asChild><Link to={section.destination}>View all</Link></Button></header>{section.preview.length ? <ul className="divide-y divide-[var(--ds-border-subtle)]">{section.preview.map((item, index) => <li key={index} className="py-3 first:pt-0 last:pb-0">{render(item)}</li>)}</ul> : <EmptyState title={empty} />}</CardContent></Card>;
}

export default function CollaborationOverview() {
  const [data, setData] = useState<Overview | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [query, setQuery] = useState("");
  const navigate = useNavigate();
  const load = () => {
    setLoading(true);
    setError("");
    void getCollaborationOverview().then(setData).catch(() => setError("Collaboration Overview could not be loaded.")).finally(() => setLoading(false));
  };
  useEffect(() => { const timer = window.setTimeout(load, 0); return () => window.clearTimeout(timer); }, []);
  if (loading) return <LoadingState label="Loading Collaboration Overview..." />;
  if (error || !data) return <ErrorState description={error || "Collaboration Overview is unavailable."} retry={load} />;

  const sections = data.sections;
  return <div className="mx-auto max-w-7xl space-y-6">
    <header><PageTitle>Collaboration</PageTitle><p className="mt-1 text-sm text-[var(--ds-text-muted)]">Your current shared work, updates, notifications, and collaboration activity.</p></header>
    <form className="flex flex-col gap-2 sm:flex-row" onSubmit={(event) => { event.preventDefault(); if (query.trim()) navigate(`/app/search?q=${encodeURIComponent(query.trim())}`); }}><Input label="Search resources" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search authorized resources"/><Button type="submit" className="sm:self-end">Search</Button></form>
    <div className="grid gap-4 lg:grid-cols-2">
      <Summary section={sections.myWork} empty="No current work items are available in this workspace." render={(row) => <Link className="block min-w-0" to={row.destination}><span className="break-words font-medium">{row.label}</span><span className="mt-1 flex flex-wrap gap-1">{row.relationshipKeys.map((key) => <Badge key={key}>{key}</Badge>)}<Badge>{row.lifecycle}</Badge></span></Link>}/>
      <Summary section={sections.sharedWithMe} empty="No resources have been shared directly with you." render={(row) => <Link className="block" to={row.destination}><span className="break-words font-medium">{row.label}</span><span className="mt-1 flex gap-1"><Badge>{row.accessLabel}</Badge><Badge>{row.lifecycle}</Badge></span></Link>}/>
      <Summary section={sections.changes} empty="No updates are waiting to be pulled." render={(row) => <Link className="block" to={sections.changes.destination}><span className="break-words font-medium">{row.resource.name}</span><span className="mt-1 block text-xs text-[var(--ds-text-muted)]">Revision {row.latestRevision.revisionNumber} · {row.pendingRevisionCount} pending</span></Link>}/>
      <Summary section={sections.notifications} empty="No unread notifications." countLabel={<>{sections.notifications.count} unread {sections.notifications.count === 1 ? "notification" : "notifications"}</>} render={(row) => <Link className="block" to={row.deepLink || sections.notifications.destination}><span className="break-words font-medium">{row.title}</span><span className="mt-1 block break-words text-xs text-[var(--ds-text-muted)]">Unread{row.requiresAction ? " · Action required" : ""} · {new Date(row.createdAt).toLocaleString()}</span></Link>}/>
    </div>
    <Card><CardContent className="space-y-4"><header className="flex items-center justify-between gap-3"><h2 className="font-semibold">Recent Collaboration Activity</h2><Button size="compact" variant="outline" asChild><Link to={sections.activity.destination}>View activity</Link></Button></header><ActivityTimeline events={sections.activity.preview}/></CardContent></Card>
    {data.adminShortcuts.length > 0 && <Card><CardContent><h2 className="font-semibold">Administration</h2><nav aria-label="Administration shortcuts" className="mt-3 flex flex-wrap gap-2">{data.adminShortcuts.map((item) => <Button key={item.destination} size="small" variant="outline" asChild><Link to={item.destination}>{item.label}</Link></Button>)}</nav></CardContent></Card>}
  </div>;
}
