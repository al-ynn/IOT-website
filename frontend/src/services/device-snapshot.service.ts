import api from "./api";import type {DeviceSnapshot,SnapshotDifference} from "../types/device-snapshot";
export async function listDeviceSnapshots(deviceId:string){const response=await api.get<{data:DeviceSnapshot[]}>(`/devices/${deviceId}/snapshots`);return response.data.data;}
export async function createDeviceSnapshot(deviceId:string,payload:{name:string;description?:string}){const response=await api.post<{data:DeviceSnapshot}>(`/devices/${deviceId}/snapshots`,payload);return response.data.data;}
export async function compareDeviceSnapshots(deviceId:string,from:string,to:string){const response=await api.post<{data:{differences:SnapshotDifference[]}}>(`/devices/${deviceId}/snapshots/compare`,{from_snapshot_id:from,to_snapshot_id:to});return response.data.data.differences;}
