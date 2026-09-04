import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  Card,
  CardContent,
  EmptyState,
  ErrorState,
  LoadingState,
  PageTitle,
} from "../../components/ui";
import { getSharedWithMe } from "../../services/shared-with-me.service";
import type { SharedResourcePage } from "../../types/shared-with-me";

export default function SharedWithMe() {
  const [response, setResponse] = useState<{
    key: string;
    page?: SharedResourcePage;
    error?: string;
  } | null>(null);
  const [query, setQuery] = useState("");
  const [submitted, setSubmitted] = useState("");
  const [resourceType, setResourceType] = useState("");
  const [lifecycle, setLifecycle] = useState("");
  const [pageNumber, setPageNumber] = useState(1);
  const requestKey = [submitted, resourceType, lifecycle, pageNumber].join("|");
  useEffect(() => {
    const controller = new AbortController();
    getSharedWithMe(
      {
        q: submitted || undefined,
        resource_type: resourceType || undefined,
        lifecycle: lifecycle || undefined,
        page: pageNumber,
        per_page: 25,
      },
      controller.signal,
    )
      .then((page) => setResponse({ key: requestKey, page }))
      .catch((error: unknown) => {
        if (
          !(
            error instanceof Error &&
            (error.name === "CanceledError" || error.name === "AbortError")
          )
        ) {
          setResponse({
            key: requestKey,
            error: "Shared resources could not be loaded.",
          });
        }
      });
    return () => controller.abort();
  }, [lifecycle, pageNumber, requestKey, resourceType, submitted]);
  const current = response?.key === requestKey ? response : null;
  if (!current) return <LoadingState label="Loading shared resources..." />;
  if (current.error) return <ErrorState description={current.error} />;
  const page = current.page;
  return (
    <div className="space-y-5">
      <div>
        <PageTitle>Shared With Me</PageTitle>
        <p className="mt-1 text-sm text-[var(--ds-text-muted)]">
          Original resources you can access through a current direct grant.
        </p>
      </div>
      <form
        className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto]"
        onSubmit={(event) => {
          event.preventDefault();
          setPageNumber(1);
          setSubmitted(query.trim());
        }}
        role="search"
      >
        <label className="sr-only" htmlFor="shared-resource-search">
          Search shared resources
        </label>
        <input
          id="shared-resource-search"
          className="min-w-0 flex-1 rounded-md border px-3 py-2"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Search by resource name or ID"
        />
        <button className="rounded-md border px-4 py-2" type="submit">
          Search
        </button>
        <label className="sr-only" htmlFor="shared-resource-type">
          Resource type
        </label>
        <select
          id="shared-resource-type"
          className="rounded-md border px-3 py-2"
          value={resourceType}
          onChange={(event) => {
            setResourceType(event.target.value);
            setPageNumber(1);
          }}
        >
          <option value="">All resource types</option>
          <option value="device">Devices</option>
          <option value="device_template">Templates</option>
          <option value="dashboard">Dashboards</option>
          <option value="automation">Automations</option>
          <option value="report">Reports</option>
          <option value="webhook">Webhooks</option>
          <option value="location">Locations</option>
          <option value="firmware">Firmware</option>
        </select>
        <label className="sr-only" htmlFor="shared-resource-lifecycle">
          Lifecycle
        </label>
        <select
          id="shared-resource-lifecycle"
          className="rounded-md border px-3 py-2"
          value={lifecycle}
          onChange={(event) => {
            setLifecycle(event.target.value);
            setPageNumber(1);
          }}
        >
          <option value="">All lifecycle states</option>
          <option value="active">Active</option>
          <option value="disabled">Disabled</option>
          <option value="archived">Archived</option>
        </select>
      </form>
      {page?.data.length ? (
        <ul className="grid gap-3 md:grid-cols-2" aria-label="Shared resources">
          {page.data.map((item) => (
            <li key={item.resourceType + ":" + item.resourceId}>
              <Card>
                <CardContent>
                  <Link
                    className="font-semibold text-[var(--ds-primary)]"
                    to={item.destination}
                  >
                    {item.label}
                  </Link>
                  <p className="mt-1 text-sm text-[var(--ds-text-muted)]">
                    {item.resourceType.replaceAll("_", " ")} ·{" "}
                    {item.accessLabel}
                  </p>
                  <p className="mt-2 text-xs">{item.lifecycle}</p>
                  {item.grantedBy ? (
                    <p className="mt-1 text-xs">
                      Granted by {item.grantedBy.name}
                    </p>
                  ) : null}
                  {item.grantedAt ? (
                    <time
                      className="mt-1 block text-xs"
                      dateTime={item.grantedAt}
                    >
                      {new Date(item.grantedAt).toLocaleDateString()}
                    </time>
                  ) : null}
                </CardContent>
              </Card>
            </li>
          ))}
        </ul>
      ) : (
        <EmptyState
          title="Nothing shared with you"
          description="Resources appear here only after a current direct access grant is created."
        />
      )}
      {page && page.last_page > 1 ? (
        <nav
          aria-label="Shared resources pagination"
          className="flex items-center justify-between gap-3"
        >
          <button
            type="button"
            className="rounded-md border px-4 py-2 disabled:opacity-50"
            disabled={page.current_page <= 1}
            onClick={() => setPageNumber((value) => Math.max(1, value - 1))}
          >
            Previous
          </button>
          <p className="text-sm" aria-live="polite">
            Page {page.current_page} of {page.last_page}
          </p>
          <button
            type="button"
            className="rounded-md border px-4 py-2 disabled:opacity-50"
            disabled={page.current_page >= page.last_page}
            onClick={() => setPageNumber((value) => value + 1)}
          >
            Next
          </button>
        </nav>
      ) : null}
    </div>
  );
}
