import type {CollaborationCapabilities} from "./types";

export const unavailableCollaborationCapabilities:CollaborationCapabilities={
  sharing:false,
  comments:false,
  revisions:false,
  review:false,
  lifecycle:false,
  notifications:false,
  sectionComments:false,
};

export function collaborationCapability(
  capabilities:CollaborationCapabilities|undefined,
  capability:keyof CollaborationCapabilities,
):boolean{
  return capabilities?.[capability]===true;
}
