export type ReviewCenterResourceType =
  | "device_template"
  | "dashboard"
  | "automation"
  | "report"
  | "webhook"
  | "firmware";

export type AdminReviewState =
  | "all_active"
  | "available"
  | "in_review"
  | "mine"
  | "reviewer_unavailable";

export interface AdminReviewSummary {
  active: number;
  available: number;
  inReview: number;
  mine: number;
  reviewerUnavailable: number;
  byResourceType: Record<ReviewCenterResourceType, number>;
}

export interface AdminReviewItem {
  submissionId: string;
  reviewKind: "publication" | "activation" | "release";
  resourceType: ReviewCenterResourceType;
  resourceTypeLabel: string;
  resourceId: string;
  resourceLabel: string;
  organization: { id: string; name: string } | null;
  submittedRevision: { id: string; number: number };
  latestRevision: { id: string; number: number } | null;
  hasNewerDraftRevision: boolean;
  submittedBy: { id: string; name: string; inactive: boolean } | null;
  submittedAt: string;
  reviewState: "available" | "mine" | "in_review" | "reviewer_unavailable";
  reviewer: { id: string; name: string } | null;
  reviewClaimedAt: string | null;
  currentPublication: { id: string; number: number; revisionNumber: number } | null;
  capabilities: {
    canClaim: boolean;
    canRelease: boolean;
    canTakeOver: boolean;
    canApprove: boolean;
    canRequestChanges: boolean;
    canReject: boolean;
  };
  reviewDeepLink: string;
}

export interface AdminReviewPage {
  data: AdminReviewItem[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
