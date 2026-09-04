import api from "./api";import type {CreatedDeviceCredential,DeviceCredential,DeviceCredentialScope} from "../types/device-credential";
const base=(deviceId:string,admin=false)=>`${admin?"/admin":""}/devices/${deviceId}/credentials`;
export async function listAllDeviceCredentials(){return(await api.get<{data:DeviceCredential[]}>("/device-credentials")).data.data;}
export async function listDeviceCredentials(deviceId:string,admin=false){return(await api.get<{data:DeviceCredential[]}>(base(deviceId,admin))).data.data;}
export async function createDeviceCredential(deviceId:string,payload:{name:string;scopes:DeviceCredentialScope[];expires_at?:string|null},admin=false){return(await api.post<CreatedDeviceCredential>(base(deviceId,admin),payload)).data;}
export async function revokeDeviceCredential(deviceId:string,credentialId:string,admin=false){return(await api.post<{data:DeviceCredential}>(`${base(deviceId,admin)}/${credentialId}/revoke`)).data.data;}
