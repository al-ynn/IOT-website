import { useEffect, useState } from "react";
import {
  Link,
  useLocation,
  useParams,
  useSearchParams,
} from "react-router-dom";
import CommentsPanel from "../../components/collaboration/CommentsPanel";
import RevisionHistoryPanel from "../../components/collaboration/RevisionHistoryPanel";
import ResourceTabs from "../../components/layout/ResourceTabs";
import {
  BodyText,
  Button,
  Card,
  CardContent,
  CardHeader,
  EmptyState,
  ErrorState,
  Input,
  LoadingState,
  PageTitle,
  Textarea,
} from "../../components/ui";
import { resolveResourceTabs } from "../../navigation/resource-tabs";
import {
  getLocation,
  listLocationDevices,
  updateStaffLocation,
} from "../../services/location.service";
import type { Location as LocationType } from "../../types/location";
import { useEditingPresence } from "../../hooks/useEditingPresence";

export default function LocationDetail() {
  const { id = "" } = useParams(),
    admin = useLocation().pathname.startsWith("/admin/"),
    [params] = useSearchParams();
  const basePath = `${admin ? "/admin" : "/app"}/locations/${id}`;
  const resolved = resolveResourceTabs("location", params.get("tab"), {
      admin,
      capabilities: {},
    }),
    tab = resolved.activeKey;
  const [item, setItem] = useState<LocationType | null>(null),
    [devices, setDevices] = useState<
      Array<{
        id: string;
        name: string;
        external_id: string | null;
        status: string;
      }>
    >([]),
    [name, setName] = useState(""),
    [description, setDescription] = useState(""),
    [error, setError] = useState("");
  const { otherEditors, presenceUnavailable } = useEditingPresence(
    "location",
    id,
    !admin && tab === "overview" && Boolean(item?.capabilities?.canUpdate),
  );
  useEffect(() => {
    let active = true;
    getLocation(id, admin)
      .then((location) => {
        if (active) {
          setItem(location);
          setName(location.name);
          setDescription(location.description ?? "");
        }
      })
      .catch(() => {
        if (active)
          setError(
            "Location workspace is unavailable or you no longer have access.",
          );
      });
    return () => {
      active = false;
    };
  }, [id, admin]);
  useEffect(() => {
    if (tab !== "overview") return;
    let active = true;
    listLocationDevices(id, admin)
      .then((page) => {
        if (active) setDevices(page.data);
      })
      .catch(() => {
        if (active) setDevices([]);
      });
    return () => {
      active = false;
    };
  }, [id, admin, tab]);
  async function save(event: React.FormEvent) {
    event.preventDefault();
    try {
      const next = await updateStaffLocation(id, {
        name,
        description: description || null,
      });
      setItem(next);
      setError("");
    } catch {
      setError("Location changes could not be saved.");
    }
  }
  if (error && !item) return <ErrorState description={error} />;
  if (!item) return <LoadingState label="Loading Location..." />;
  const tabItems = resolved.tabs.map((definition) => ({
    id: definition.key,
    label: definition.label,
    to:
      definition.key === "overview"
        ? basePath
        : `${basePath}?tab=${definition.key}`,
  }));
  return (
    <div className="space-y-5">
      <div>
        <PageTitle>{item.name}</PageTitle>
        <BodyText className="mt-1">
          {item.description ?? "No description"} · {item.lifecycle} ·{" "}
          {item.access ?? "reference"}
        </BodyText>
      </div>
      {otherEditors.length > 0 && (
        <p
          role="status"
          className="rounded border border-[var(--ds-warning)] p-3 text-sm"
        >
          {otherEditors.map((editor) => editor.user.displayName).join(", ")}{" "}
          {otherEditors.length === 1 ? "is" : "are"} also editing. Saves remain
          available and stale revisions are rejected.
        </p>
      )}
      {presenceUnavailable && (
        <p role="status" className="text-xs text-[var(--ds-text-muted)]">
          Editing presence is temporarily unavailable; save conflict protection
          remains active.
        </p>
      )}
      <ResourceTabs items={tabItems} activeId={tab} label="Location sections" />
      <section aria-label={`${tab} location section`}>
        {tab === "comments" ? (
          <CommentsPanel
            resourceId={id}
            resourceType="location"
            canResolve={item.capabilities?.canUpdate}
          />
        ) : tab === "revisions" ? (
          <RevisionHistoryPanel resourceId={id} resourceType="location" />
        ) : (
          <div className="space-y-4">
            {!admin && item.capabilities?.canUpdate && (
              <Card>
                <CardHeader
                  title="Location metadata"
                  description="Shared immediately and recorded as one canonical revision."
                />
                <CardContent>
                  <form className="space-y-3" onSubmit={save}>
                    <Input
                      label="Name"
                      value={name}
                      maxLength={120}
                      onChange={(event) => setName(event.target.value)}
                    />
                    <Textarea
                      label="Description"
                      value={description}
                      maxLength={1000}
                      onChange={(event) => setDescription(event.target.value)}
                    />
                    <Button type="submit">Save changes</Button>
                  </form>
                </CardContent>
              </Card>
            )}
            <Card>
              <CardHeader
                title={admin ? "Devices" : "Devices you can access"}
                description={`${devices.length} shown on this page`}
              />
              <CardContent>
                {devices.length ? (
                  <ul className="divide-y divide-[var(--ds-border-subtle)]">
                    {devices.map((device) => (
                      <li
                        key={device.id}
                        className="flex items-center justify-between gap-3 py-3 text-sm"
                      >
                        <Link
                          className="text-[var(--ds-primary)]"
                          to={`${admin ? "/admin" : "/app"}/devices/${device.id}`}
                        >
                          {device.name}
                        </Link>
                        <span>{device.status}</span>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <EmptyState
                    title="No accessible Devices"
                    description={
                      admin
                        ? "No Devices currently reference this Location."
                        : "You do not currently have access to any Devices at this Location."
                    }
                  />
                )}
              </CardContent>
            </Card>
          </div>
        )}
      </section>
    </div>
  );
}
