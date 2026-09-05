/* eslint-disable react-hooks/set-state-in-effect */
import { useCallback, useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import {
  BodyText,
  Button,
  Card,
  CardContent,
  CardHeader,
  ErrorState,
  LoadingState,
  PageTitle,
  Textarea,
} from "../../components/ui";
import {
  approveAdminReview,
  claimAdminReview,
  getAdminReviewSubmission,
  rejectAdminReview,
  releaseAdminReview,
  requestAdminReviewChanges,
  takeoverAdminReview,
} from "../../services/admin.service";
import type {
  AdminReviewItem,
  ReviewCenterResourceType,
} from "../../types/admin-review";

const validTypes: ReviewCenterResourceType[] = [
  "device_template",
  "dashboard",
  "automation",
  "report",
  "webhook",
  "firmware",
];

export default function AdminReviewDetail() {
  const { id = "", type = "" } = useParams();
  const resourceType = type === ""
    ? "device_template"
    : validTypes.includes(type as ReviewCenterResourceType)
      ? type as ReviewCenterResourceType
      : null;
  const navigate = useNavigate();
  const [item, setItem] = useState<AdminReviewItem | null>(null);
  const [note, setNote] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!resourceType) {
      setError("This review type is not supported by the Review Center.");
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      setItem(await getAdminReviewSubmission(id, resourceType));
      setError("");
    } catch {
      setError("Review submission could not be loaded.");
    } finally {
      setLoading(false);
    }
  }, [id, resourceType]);

  useEffect(() => {
    void load();
  }, [load]);

  const ownership = async (action: "claim" | "release" | "takeover") => {
    if (!item || !resourceType) return;
    const prompt = action === "release"
      ? "Release this review for another eligible Admin?"
      : "Take over this review claim? This does not approve or modify the resource.";
    if (action !== "claim" && !confirm(prompt)) return;
    setSaving(true);
    try {
      if (action === "claim") await claimAdminReview(id, resourceType);
      else if (action === "release") await releaseAdminReview(id, resourceType);
      else await takeoverAdminReview(id, resourceType);
      await load();
    } catch {
      setError("Review ownership changed. Refresh and try again.");
    } finally {
      setSaving(false);
    }
  };

  const decide = async (action: "approve" | "changes" | "reject") => {
    if (!item || !resourceType) return;
    if ((action === "changes" || action === "reject") && !note.trim()) {
      setError("A decision note is required.");
      return;
    }
    if (!confirm(`Confirm ${action === "changes" ? "request changes" : action} for submitted Revision ${item.submittedRevision.number}?`)) return;
    setSaving(true);
    try {
      if (action === "approve") {
        await approveAdminReview(id, resourceType, item.hasNewerDraftRevision);
      } else if (action === "changes") {
        await requestAdminReviewChanges(id, resourceType, note);
      } else {
        await rejectAdminReview(id, resourceType, note);
      }
      navigate("/admin/reviews");
    } catch {
      setError("The decision was not accepted. Ensure you own this active review and refresh its state.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <LoadingState label="Loading submitted review..." />;
  if (error && !item) return <ErrorState description={error} retry={load} />;
  if (!item) return null;

  return (
    <div className="mx-auto max-w-4xl space-y-5">
      <Link to="/admin/reviews" className="text-xs text-[var(--ds-text-muted)]">
        ← Review Center
      </Link>
      <header>
        <PageTitle>{item.resourceTypeLabel} {item.reviewKind} review</PageTitle>
        <BodyText>
          Decision scope is pinned to submitted Revision {item.submittedRevision.number}.
        </BodyText>
      </header>
      {error && <p role="alert" className="text-sm text-[var(--ds-danger)]">{error}</p>}
      <Card>
        <CardHeader
          title={item.resourceLabel}
          description={`Submitted by ${item.submittedBy?.name ?? "Unavailable account"}`}
        />
        <CardContent className="space-y-3">
          <p className="text-sm">Review state: {item.reviewState.replaceAll("_", " ")}</p>
          <p className="text-sm">Reviewer: {item.reviewer?.name ?? "Not claimed"}</p>
          {item.hasNewerDraftRevision && (
            <p className="text-sm font-medium">
              A newer draft exists. Any decision still applies only to the submitted revision.
            </p>
          )}
          <div className="flex flex-wrap gap-2">
            {item.capabilities.canClaim && <Button loading={saving} onClick={() => void ownership("claim")}>Start Review</Button>}
            {item.capabilities.canRelease && <Button variant="outline" disabled={saving} onClick={() => void ownership("release")}>Release Review</Button>}
            {item.capabilities.canTakeOver && <Button variant="outline" disabled={saving} onClick={() => void ownership("takeover")}>Take Over</Button>}
          </div>
          {(item.capabilities.canRequestChanges || item.capabilities.canReject) && (
            <Textarea
              label="Decision note"
              helperText="Required for Request Changes and Reject."
              value={note}
              onChange={(event) => setNote(event.target.value)}
            />
          )}
          <div className="flex flex-wrap gap-2">
            {item.capabilities.canApprove && <Button loading={saving} onClick={() => void decide("approve")}>Approve Submitted Revision</Button>}
            {item.capabilities.canRequestChanges && <Button variant="outline" disabled={saving} onClick={() => void decide("changes")}>Request Changes</Button>}
            {item.capabilities.canReject && <Button variant="danger" disabled={saving} onClick={() => void decide("reject")}>Reject</Button>}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
