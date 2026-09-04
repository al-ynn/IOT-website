import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import ReminderChoices from "../../components/collaboration/ReminderChoices";
import RevisionComparison from "../../components/collaboration/RevisionComparison";
import { pullUpdateService } from "../../services/pull-update.service";
import type {
  ChangesInboxItem,
  RevisionRangeSummary,
} from "../../types/pull-update";

export default function ChangesInbox() {
  const [items, setItems] = useState<ChangesInboxItem[]>([]),
    [page, setPage] = useState(1),
    [last, setLast] = useState(1);
  const [review, setReview] = useState<RevisionRangeSummary | null>(null),
    [query, setQuery] = useState(""),
    [submitted, setSubmitted] = useState("");
  const load = useCallback(
    () =>
      pullUpdateService.changes(page, submitted).then((result) => {
        setItems(result.data);
        setLast(result.last_page);
      }),
    [page, submitted],
  );
  useEffect(() => {
    void load();
  }, [load]);
  return (
    <section className="space-y-4">
      <header>
        <h1 className="text-2xl font-semibold">Changes Inbox</h1>
        <p>Pull-managed presentation updates waiting for your acceptance.</p>
      </header>
      <form
        role="search"
        className="flex gap-2"
        onSubmit={(event) => {
          event.preventDefault();
          setPage(1);
          setSubmitted(query.trim());
        }}
      >
        <label htmlFor="changes-search" className="sr-only">
          Search changes
        </label>
        <input
          id="changes-search"
          className="min-w-0 flex-1 rounded border px-3 py-2"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Search by resource name or ID"
        />
        <button type="submit" className="rounded border px-3 py-2">
          Search
        </button>
      </form>
      <ul className="space-y-3">
        {items.map((item) => (
          <li key={item.resourceId} className="rounded-md border p-4">
            <Link to={`/app/devices/${item.resource.id}?tab=dashboard`}>
              {item.resource.name}
            </Link>
            <p>
              Revision {item.acceptedRevision.revisionNumber} →{" "}
              {item.latestRevision.revisionNumber} · {item.pendingRevisionCount}{" "}
              pending
            </p>
            <p>
              Updated by {item.latestRevision.createdBy?.name ?? "System"} ·{" "}
              {item.pullableChangedSections.join(", ")}
            </p>
            <p className="text-xs">Lifecycle: {item.lifecycle}</p>
            <div className="flex flex-wrap gap-2">
              {item.actions.canReview && (
                <button
                  onClick={() =>
                    pullUpdateService
                      .review("device", item.resource.id)
                      .then(setReview)
                  }
                >
                  Review Changes
                </button>
              )}
              {item.actions.canPull && (
                <button
                  onClick={() =>
                    pullUpdateService
                      .pull("device", item.resource.id)
                      .then(load)
                  }
                >
                  Pull Update
                </button>
              )}
              {item.actions.canIgnore && (
                <button
                  onClick={() =>
                    pullUpdateService
                      .ignore("device", item.resource.id)
                      .then(load)
                  }
                >
                  Ignore
                </button>
              )}
              {item.actions.canRemind && (
                <ReminderChoices resourceId={item.resource.id} />
              )}
            </div>
          </li>
        ))}
      </ul>
      {items.length === 0 && <p role="status">You are up to date.</p>}
      {last > 1 && (
        <nav aria-label="Changes pages">
          <button
            disabled={page === 1}
            onClick={() => setPage((value) => value - 1)}
          >
            Previous
          </button>{" "}
          Page {page} of {last}{" "}
          <button
            disabled={page === last}
            onClick={() => setPage((value) => value + 1)}
          >
            Next
          </button>
        </nav>
      )}
      {review && (
        <section
          role="dialog"
          aria-modal="true"
          aria-label="Revision comparison"
          className="fixed inset-4 z-50 overflow-auto rounded border bg-[var(--ds-surface)] p-4 shadow-xl sm:inset-12 lg:inset-24"
        >
          <RevisionComparison comparison={review} />
          <button
            type="button"
            className="mt-4 rounded border px-3 py-1"
            onClick={() => setReview(null)}
          >
            Close
          </button>
        </section>
      )}
    </section>
  );
}
