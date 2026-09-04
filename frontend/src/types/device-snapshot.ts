export interface DeviceSnapshot{ id:string;deviceId:string;name:string;description:string|null;payload:{device:Record<string,string|null>;template:Record<string,string|null>|null;parameters:Array<Record<string,string|null>>};capturedAt:string;createdBy:{id:string;name:string}|null }
export interface SnapshotDifference{path:string;before:unknown;after:unknown}
