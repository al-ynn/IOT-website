export interface RevisionAuthor{id:string;name:string}
export interface ResourceRevision{id:string;revisionNumber:number;createdBy:RevisionAuthor|null;createdAt:string;changedSections:string[];changeSummary:string;isCurrent:boolean}
export interface DeviceRevisionSnapshot{metadata:Record<string,string|number|null>;dashboard:{id:number;name:string;description:string|null;widgets:Array<Record<string,unknown>>}|null;parameters:Array<Record<string,unknown>>}
export interface RevisionDetail extends ResourceRevision{snapshotSchemaVersion:number;snapshot:DeviceRevisionSnapshot|{metadata:Record<string,string|number|null>}}
export interface RevisionPage{data:ResourceRevision[];current_page:number;last_page:number;total:number}
