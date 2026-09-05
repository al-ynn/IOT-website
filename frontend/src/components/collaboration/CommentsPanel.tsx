import { useEffect, useState } from "react";
import {
  Button,
  Card,
  CardContent,
  EmptyState,
  ErrorState,
  Select,
  Skeleton,
  Textarea,
} from "../ui";
import {
  acknowledgeThread,
  createThread,
  listThreads,
  reopenThread,
  replyThread,
  resolveThread,
  type ThreadAnchor,
} from "../../services/comment.service";
import type { CollaborationThread } from "../../types/comment";
export default function CommentsPanel({
  resourceId,
  resourceType = "device",
  canResolve = false,
  anchor = { type: "resource" },
  contextLabel = "Dashboard",
}: {
  resourceId: string;
  resourceType?: string;
  canResolve?: boolean;
  anchor?: ThreadAnchor;
  contextLabel?: string;
}) {
  const [items, setItems] = useState<CollaborationThread[]>([]),
    [status, setStatus] = useState<"open" | "resolved">("open"),
    [body, setBody] = useState(""),
    [reply, setReply] = useState<Record<string, string>>({}),
    [loading, setLoading] = useState(true),
    [error, setError] = useState(""),
    [rev, setRev] = useState(0);
  const anchorKey = anchor.key ?? null;
  useEffect(() => {
    let active = true;
    listThreads(resourceType, resourceId, status, {
      type: anchor.type,
      key: anchorKey,
    })
      .then((r) => {
        if (active) {
          setItems(r.data);
          setError("");
        }
      })
      .catch(() => {
        if (active) setError("Comments could not be loaded.");
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [resourceId, resourceType, status, rev, anchor.type, anchorKey]);
  const refresh = () => setRev((v) => v + 1);
  const add = async () => {
    if (!body.trim()) return;
    await createThread(resourceType, resourceId, body.trim(), {
      ...anchor,
      key: anchorKey,
    });
    setBody("");
    refresh();
  };
  const respond = async (id: string) => {
    if (!reply[id]?.trim()) return;
    await replyThread(id, reply[id]);
    setReply((v) => ({ ...v, [id]: "" }));
    refresh();
  };
  return (
    <section aria-label={`${contextLabel} comments`} className="space-y-4">
      <Card>
        <CardContent className="space-y-3">
          <div>
            <h2 className="text-sm font-semibold">{contextLabel} discussion</h2>
            <p className="text-xs text-[var(--ds-text-muted)]">
              {anchor.type === "dashboard_widget"
                ? "Anchored to this stable Widget instance. Moving or resizing it will not move the thread."
                : "Anchored to the whole Dashboard."}
            </p>
          </div>
          <Textarea
            label={`Start a discussion about ${contextLabel}`}
            maxLength={5000}
            value={body}
            onChange={(e) => setBody(e.target.value)}
            helperText={`${body.length}/5000 · plain text`}
          />
          <Button disabled={!body.trim()} onClick={() => void add()}>
            Start thread
          </Button>
        </CardContent>
      </Card>
      <Select
        aria-label="Thread status"
        value={status}
        onChange={(e) => {
          setLoading(true);
          setStatus(e.target.value as typeof status);
        }}
      >
        <option value="open">Open threads</option>
        <option value="resolved">Resolved threads</option>
      </Select>
      {error ? (
        <ErrorState description={error} retry={refresh} />
      ) : loading ? (
        <Skeleton className="h-48" />
      ) : items.length === 0 ? (
        <EmptyState title={`No ${status} discussions for ${contextLabel}`} />
      ) : (
        <ul className="space-y-3">
          {items.map((t) => (
            <li key={t.id}>
              <Card>
                <CardContent className="space-y-3">
                  <div className="flex flex-wrap justify-between gap-2">
                    <div>
                      <p className="text-xs font-semibold">
                        {t.anchorContext.current.label} · {t.status}
                      </p>
                      {t.anchorContext.origin?.available && (
                        <p className="text-[11px] text-[var(--ds-text-muted)]">
                          Commented in Revision{" "}
                          {t.anchorContext.origin.revisionNumber}
                        </p>
                      )}
                    </div>
                    <div className="flex gap-2">
                      <Button
                        size="compact"
                        variant="outline"
                        onClick={() =>
                          void acknowledgeThread(t.id).then(refresh)
                        }
                      >
                        Acknowledge
                      </Button>
                      {canResolve && (
                        <Button
                          size="compact"
                          variant="outline"
                          onClick={() =>
                            void (
                              t.status === "resolved"
                                ? reopenThread(t.id)
                                : resolveThread(t.id)
                            ).then(refresh)
                          }
                        >
                          {t.status === "resolved" ? "Reopen" : "Resolve"}
                        </Button>
                      )}
                    </div>
                  </div>
                  {t.anchorContext.change.message && (
                    <div
                      role="status"
                      className="rounded border border-[var(--ds-border)] p-2 text-xs"
                    >
                      <p>{t.anchorContext.change.message}</p>
                      {t.anchorContext.change.fromRevisionId &&
                        t.anchorContext.change.toRevisionId && (
                          <a
                            className="underline"
                            href={`?tab=dashboard&compareFrom=${t.anchorContext.change.fromRevisionId}&compareTo=${t.anchorContext.change.toRevisionId}&focus=${encodeURIComponent(t.anchorContext.change.focus ?? "")}`}
                          >
                            Review Changes
                          </a>
                        )}
                    </div>
                  )}
                  {t.anchorContext.current.navigation && (
                    <a
                      className="inline-flex min-h-9 items-center text-xs font-medium underline"
                      href={`?tab=${encodeURIComponent(t.anchorContext.current.navigation.tab ?? "overview")}&focus=${encodeURIComponent(t.anchorContext.current.navigation.focus ?? "")}`}
                    >
                      Go to current context
                    </a>
                  )}
                  <ol className="space-y-2">
                    {t.comments.map((c) => (
                      <li
                        key={c.id}
                        className="rounded border border-[var(--ds-border-subtle)] p-3"
                      >
                        <p className="text-[10px]">
                          {c.author?.name ?? "Former user"} ·{" "}
                          {new Date(c.createdAt).toLocaleString()}
                        </p>
                        <p className="mt-1 whitespace-pre-wrap break-words text-xs">
                          {c.body}
                        </p>
                      </li>
                    ))}
                  </ol>
                  {t.status === "open" && (
                    <div className="flex flex-col gap-2 sm:flex-row">
                      <Textarea
                        aria-label={`Reply to thread ${t.id}`}
                        value={reply[t.id] ?? ""}
                        maxLength={5000}
                        onChange={(e) =>
                          setReply((v) => ({ ...v, [t.id]: e.target.value }))
                        }
                      />
                      <Button size="small" onClick={() => void respond(t.id)}>
                        Reply
                      </Button>
                    </div>
                  )}
                </CardContent>
              </Card>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
