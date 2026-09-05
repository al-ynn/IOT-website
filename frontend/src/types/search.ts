export interface SearchResult {
  resultKind: "resource";
  resourceType: string;
  resourceId: string;
  label: string;
  lifecycle: "active" | "disabled" | "archived";
  accessMode: string;
  matchKind: "exact_id" | "exact_label" | "label_prefix" | "label_contains" | "metadata" | "child_metadata";
  matchedFieldLabel: string;
  matchedContext: string;
  destination: string;
}

export interface SearchResponse {
  data: SearchResult[];
  current_page: number;
  last_page: number;
  total: number;
}
