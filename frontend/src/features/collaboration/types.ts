export type CollaborationResourceType=
  | "device"
  | "device_template"
  | "dashboard"
  | "automation"
  | "report"
  | "firmware"
  | "webhook";

export type CollaborationPermission="view"|"edit"|"review"|"viewer"|"full_access";

export interface CollaborationResourceReference{type:CollaborationResourceType;id:string}
export interface CollaborationCapabilities{
  sharing:boolean;
  comments:boolean;
  revisions:boolean;
  review:boolean;
  lifecycle:boolean;
  notifications:boolean;
  sectionComments:boolean;
}
