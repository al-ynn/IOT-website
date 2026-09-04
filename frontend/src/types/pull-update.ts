import type { ResourceRevision } from "./revision";
export interface ResourceRevisionState {
  resourceType: string;
  resourceId: string;
  acceptedRevision: ResourceRevision;
  latestRevision: ResourceRevision;
  updateAvailable: boolean;
  pullableChangedSections: string[];
  ignoredRevisionId: string | null;
}
export type RevisionChangeType = "added" | "removed" | "changed" | "moved";
export interface RevisionDiffEntry {
  identity: string;
  label: string;
  changeType: RevisionChangeType;
  before: unknown;
  after: unknown;
  metadata: {
    changes?: Array<{
      field: string;
      changeType: RevisionChangeType;
      before: unknown;
      after: unknown;
    }>;
  };
}
export interface RevisionDiffSection {
  key: string;
  label: string;
  changeType: RevisionChangeType;
  entries: RevisionDiffEntry[];
}
export interface RevisionRangeSummary {
  resource: { type: string; id: string; label: string };
  fromRevision: ResourceRevision;
  toRevision: ResourceRevision;
  sections: RevisionDiffSection[];
  revisions: ResourceRevision[];
  pullableChangedSections: string[];
  summary: {
    changedSections: string[];
    changeCount: number;
    intermediateRevisions: ResourceRevision[];
    contributors: Array<{ id: string; name: string }>;
  };
  focus: string | null;
}
export interface ChangesInboxItem extends ResourceRevisionState {
  resource: { id: string; name: string; type: "device" };
  lifecycle: "active" | "disabled" | "archived";
  pendingRevisionCount: number;
  actions: {
    canReview: boolean;
    canPull: boolean;
    canIgnore: boolean;
    canRemind: boolean;
  };
}
export interface ChangesPage {
  data: ChangesInboxItem[];
  current_page: number;
  last_page: number;
  total: number;
}
export type RevisionReminderPreset =
  "one_hour" | "three_hours" | "tomorrow" | "none";
export interface RevisionReminderState {
  reminderPreset: RevisionReminderPreset | null;
  remindAt: string | null;
  isSnoozed: boolean;
  status: string | null;
  generation: number;
  effectiveTimezone: string;
  targetType?: string;
  resourceType?: string;
}
