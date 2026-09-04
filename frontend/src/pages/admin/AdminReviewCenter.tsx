/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  Button,
  Card,
  CardContent,
  Input,
  LoadingState,
  PageTitle,
  Select,
} from "../../components/ui";
import {
  claimAdminReview,
  getAdminReviewSummary,
  listAdminReviews,
  releaseAdminReview,
  takeoverAdminReview,
} from "../../services/admin.service";
import type {
  AdminReviewItem,
  AdminReviewState,
  AdminReviewSummary,
  ReviewCenterResourceType,
} from "../../types/admin-review";
export default function AdminReviewCenter() {
  const [state, setState] = useState<AdminReviewState>("all_active");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState<"oldest" | "newest">("oldest");
  const [resourceType, setResourceType] = useState<ReviewCenterResourceType | "">("");
  const [page, setPage] = useState(1);
  const [summary, setSummary] = useState<AdminReviewSummary | null>(null);
  const [items, setItems] = useState<AdminReviewItem[]>([]);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [mutatingId, setMutatingId] = useState<string | null>(null);
  const [error, setError] = useState("");
  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [s, p] = await Promise.all([
        getAdminReviewSummary({ search: search || undefined }),
        listAdminReviews({ state, resource_type: resourceType || undefined, search: search || undefined, sort, page }),
      ]);
      setSummary(s);
      setItems(p.data);
      setLastPage(p.last_page);
      setError("");
    } catch {
      setError("Review Center could not be loaded.");
    } finally {
      setLoading(false);
    }
  }, [state, resourceType, search, sort, page]);
  useEffect(() => {
    void load();
  }, [load]);
  const mutate = async (
    action: "claim" | "release" | "takeover",
    item: AdminReviewItem,
  ) => {
    const takeoverMessage = item.reviewState === "reviewer_unavailable"
      ? "The previous reviewer is no longer eligible. Take over this review claim? This will not approve or modify the resource."
      : `This review is currently handled by ${item.reviewer?.name ?? "another Admin"}. Taking over transfers only the review claim to you; it will not approve or modify the resource.`;
    if (action === "takeover" && !confirm(takeoverMessage)) return;
    if (action === "release" && !confirm("Release this review? It will become available for another Admin. It will not be rejected or withdrawn.")) return;
    setMutatingId(item.submissionId);
    try {
      if (action === "claim") await claimAdminReview(item.submissionId, item.resourceType);
      else if (action === "release") await releaseAdminReview(item.submissionId, item.resourceType);
      else await takeoverAdminReview(item.submissionId, item.resourceType);
      await load();
    } catch {
      setError(
        "Review ownership changed before this action completed. Refresh and try again.",
      );
      await load();
    } finally {
      setMutatingId(null);
    }
  };
  return (
    <div className="space-y-5">
      <header>
        <PageTitle>Admin Review Center</PageTitle>
        <p className="mt-1 text-sm text-[var(--ds-text-muted)]">
          Global active governance submissions. Decisions remain in the
          canonical review detail.
        </p>
      </header>
      {summary && (
        <section
          aria-label="Review summary"
          className="grid grid-cols-2 gap-3 lg:grid-cols-4"
        >
          {[
            ["Active", summary.active],
            ["Available", summary.available],
            ["In Review", summary.inReview],
            ["My Reviews", summary.mine],
          ].map(([label, count]) => (
            <Card key={String(label)}>
              <CardContent>
                <p className="text-xs text-[var(--ds-text-muted)]">{label}</p>
                <p
                  className="text-2xl font-semibold"
                  aria-label={`${label}: ${count}`}
                >
                  {count}
                </p>
              </CardContent>
            </Card>
          ))}
        </section>
      )}
      <Card>
        <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <Select
            label="Review state"
            value={state}
            onChange={(e) => {
              setState(e.target.value as AdminReviewState);
              setPage(1);
            }}
          >
            <option value="all_active">All Active</option>
            <option value="available">Available for Review</option>
            <option value="in_review">In Review</option>
            <option value="mine">My Reviews</option>
            <option value="reviewer_unavailable">Reviewer Unavailable</option>
          </Select>
          <Select
            label="Resource type"
            value={resourceType}
            onChange={(event) => {
              setResourceType(event.target.value as ReviewCenterResourceType | "");
              setPage(1);
            }}
          >
            <option value="">All review-enabled types</option>
            <option value="device_template">Templates</option>
            <option value="dashboard">Dashboards</option>
            <option value="automation">Automations</option>
            <option value="report">Reports</option>
            <option value="webhook">Webhooks</option>
            <option value="firmware">Firmware</option>
          </Select>
          <Input
            label="Search Reviews"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
          />
          <Select
            label="Sort"
            value={sort}
            onChange={(e) => {
              setSort(e.target.value as "oldest" | "newest");
              setPage(1);
            }}
          >
            <option value="oldest">Oldest Submitted</option>
            <option value="newest">Newest Submitted</option>
          </Select>
        </CardContent>
      </Card>
      {error && (
        <p role="alert" className="text-sm text-[var(--ds-danger)]">
          {error}
        </p>
      )}
      {loading ? (
        <LoadingState label="Loading active reviews..." />
      ) : items.length === 0 ? (
        <p className="rounded-lg border border-[var(--ds-border-subtle)] p-6 text-sm">
          No active reviews match these filters.
        </p>
      ) : (
        <section aria-label="Active review submissions" className="space-y-3">
          {items.map((item) => (
            <Card key={item.submissionId}>
              <CardContent className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div className="space-y-1">
                  <h2 className="font-semibold">{item.resourceLabel}</h2>
                  <p className="text-xs text-[var(--ds-text-muted)]">
                    {item.resourceTypeLabel} · {item.reviewKind} ·{" "}
                    {item.organization?.name ?? "Resource unavailable"}
                  </p>
                  <p className="text-sm">
                    Submitted Revision {item.submittedRevision.number}
                    {item.latestRevision &&
                      ` · Latest Revision ${item.latestRevision.number}`}
                  </p>
                  {item.hasNewerDraftRevision && (
                    <p className="text-xs font-medium">Newer Draft Exists</p>
                  )}
                  {item.currentPublication && (
                    <p className="text-xs">
                      Published Version {item.currentPublication.number} ·
                      Revision {item.currentPublication.revisionNumber}
                    </p>
                  )}
                  <p className="text-xs">
                    {item.reviewState === "available"
                      ? "Available for Review"
                      : item.reviewState === "mine"
                        ? "My Review"
                        : item.reviewState === "reviewer_unavailable"
                          ? "Reviewer Unavailable"
                          : `In Review by ${item.reviewer?.name ?? "Unavailable account"}`}{" "}
                    · Submitted by{" "}
                    {item.submittedBy?.name ?? "Unavailable account"}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Link
                    className="inline-flex items-center rounded border px-3 py-2 text-xs focus-visible:ring-2"
                    to={item.reviewDeepLink}
                  >
                    View Review
                  </Link>
                  {item.capabilities.canClaim && (
                    <Button
                      size="small"
                      loading={mutatingId === item.submissionId}
                      onClick={() => void mutate("claim", item)}
                    >
                      Start Review
                    </Button>
                  )}
                  {item.capabilities.canRelease && (
                    <Button
                      size="small"
                      variant="outline"
                      loading={mutatingId === item.submissionId}
                      onClick={() => void mutate("release", item)}
                    >
                      Release Review
                    </Button>
                  )}
                  {item.capabilities.canTakeOver && (
                    <Button
                      size="small"
                      variant="outline"
                      loading={mutatingId === item.submissionId}
                      onClick={() => void mutate("takeover", item)}
                    >
                      Take Over
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </section>
      )}
      {lastPage > 1 && (
        <nav
          aria-label="Review pages"
          className="flex items-center justify-end gap-3"
        >
          <Button
            variant="outline"
            disabled={page <= 1}
            onClick={() => setPage((value) => value - 1)}
          >
            Previous
          </Button>
          <span className="text-xs">
            Page {page} of {lastPage}
          </span>
          <Button
            variant="outline"
            disabled={page >= lastPage}
            onClick={() => setPage((value) => value + 1)}
          >
            Next
          </Button>
        </nav>
      )}
    </div>
  );
}
