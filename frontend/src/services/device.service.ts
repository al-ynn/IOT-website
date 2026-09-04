import api from "./api";
import type {CreateDeviceRequest,Device} from "../types/device";

export async function getDevices(){const response=await api.get<Device[]>("/devices");return response.data;}
export async function getDevice(id:string){const response=await api.get<Device>(`/devices/${id}`);return response.data;}
export async function createDevice(device:CreateDeviceRequest){const response=await api.post<Device>("/devices",device);return response.data;}
export async function deleteDevice(id:string){return api.delete(`/devices/${id}`);}
export async function updateDevice(id:string,device:Partial<Pick<Device,"name"|"type"|"protocol"|"macAddress">>&{location_id?:string|null;baseRevisionId?:string|null},idempotencyKey:string){const response=await api.patch<Device>(`/devices/${id}`,device,{headers:{"Idempotency-Key":idempotencyKey}});return response.data;}
