export type ReviewEventType="review.submitted"|"review.claimed"|"review.released"|"review.taken_over"|"review.changes_requested"|"review.rejected"|"review.approved";
export interface ReviewIdentity{id:string;name:string;inactive:boolean}
export interface ReviewRevision{id:string;number:number}
export interface ReviewHistoryEvent{id:string;eventType:ReviewEventType;actor:ReviewIdentity|null;timestamp:string;revision:ReviewRevision|null;previousReviewer:ReviewIdentity|null;reviewer:ReviewIdentity|null;decision:"approved"|"changes_requested"|"rejected"|null;decisionNote:string|null;previousApprovedRevision:ReviewRevision|null;approvedRevision:ReviewRevision|null;source:"live"|"legacy_backfill"}
export interface ReviewCycle{submissionId:string;status:string;current:boolean;submittedRevision:ReviewRevision|null;submittedBy:ReviewIdentity|null;submittedAt:string;events:ReviewHistoryEvent[]}
export interface ReviewHistoryPage{data:ReviewCycle[];current_page:number;last_page:number;per_page:number;total:number}
