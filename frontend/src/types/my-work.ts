export type MyWorkResourceType="device"|"device_template"|"dashboard"|"automation"|"report"|"location"|"firmware"|"webhook";
export type MyWorkRelationship="creator"|"contributor"|"draft";
export interface MyWorkItem{resourceType:MyWorkResourceType;resourceId:string;label:string;organization:{id:string;name:string};lifecycle:"active"|"disabled"|"archived";relationshipKeys:MyWorkRelationship[];relevantAt:string;destination:string}
export interface MyWorkPage{data:MyWorkItem[];current_page:number;last_page:number;per_page:number;total:number}
