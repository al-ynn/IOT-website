export interface UserProfile { id:string; name:string; email:string; createdAt:string; role?:string; organizationId?:string }
export interface PasswordUpdate { currentPassword:string; newPassword:string }
export interface NotificationPreferenceCategory { key:string; label:string; override:boolean|null; hasMandatory:boolean }
export interface NotificationPreferenceRule { key:string; category:string; label:string; description:string; mandatory:boolean; mandatoryReason:string|null; defaultEnabled:boolean; override:boolean|null; effectiveEnabled:boolean; channels:string[] }
export interface NotificationSettings { scope:"account"; channels:{key:string;label:string;enabled:boolean;mutable:boolean}[]; categories:NotificationPreferenceCategory[]; rules:NotificationPreferenceRule[] }
export interface NotificationPreferenceUpdate { categories:Record<string,boolean>; rules:Record<string,boolean> }
