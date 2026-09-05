export interface CollaborationComment {
  id: string;
  body: string;
  author: { id: string; name: string } | null;
  mentions: Array<{ id: string; name: string }>;
  createdAt: string;
}
export interface AnchorContext {
  schemaVersion: number;
  type: "RESOURCE" | "SECTION" | "CHILD_ENTITY" | "UNRESOLVABLE";
  semanticKey: string | null;
  current: {
    exists: boolean;
    label: string;
    focus: string | null;
    navigation: { tab: string | null; focus: string | null } | null;
  };
  origin: {
    revisionId: string | null;
    revisionNumber: number | null;
    available: boolean;
  } | null;
  change: {
    status: "unchanged" | "changed" | "moved" | "removed" | "unknown";
    message: string | null;
    focus: string | null;
    fromRevisionId?: string;
    toRevisionId?: string;
  };
}
export interface CollaborationThread {
  id: string;
  anchor: {
    type: string;
    key: string | null;
    revisionId: string | null;
    schemaVersion: number;
  };
  anchorContext: AnchorContext;
  revisionContext: {
    status: "unchanged" | "changed" | "removed";
    message: string | null;
    focus: string | null;
    fromRevisionId?: string;
    toRevisionId?: string;
  } | null;
  status: "open" | "resolved";
  creator: { id: string; name: string } | null;
  comments: CollaborationComment[];
  acknowledgments: Array<{
    user: { id: string; name: string };
    acknowledgedAt: string;
  }>;
  createdAt: string;
}
export interface ThreadList {
  data: CollaborationThread[];
  current_page: number;
  last_page: number;
  total: number;
}
