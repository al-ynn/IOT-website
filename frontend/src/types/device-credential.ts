export type DeviceCredentialScope="telemetry:write"|"firmware:read"|"crash:write";
export interface DeviceCredential{id:string;name:string;prefix:string;scopes:DeviceCredentialScope[];device:{id:string;name:string;identifier:string}|null;createdBy:{id:string;name:string}|null;createdAt:string;lastUsedAt:string|null;expiresAt:string|null;revokedAt:string|null;status:"active"|"expired"|"revoked"}
export interface CreatedDeviceCredential{data:DeviceCredential;token:string}
