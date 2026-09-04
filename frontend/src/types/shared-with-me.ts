export interface SharedResource {
  resourceType: string;
  resourceId: string;
  label: string;
  organization: { id: string; name: string };
  lifecycle: "active" | "disabled" | "archived";
  accessKey: "viewer" | "full_access" | "view" | "edit";
  accessLabel: string;
  grantedAt: string | null;
  grantedBy: { id: string; name: string } | null;
  destination: string;
}
export interface SharedResourcePage {
  data: SharedResource[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
