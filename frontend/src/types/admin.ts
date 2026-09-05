export type OrganizationStatus =
    | "active"
    | "suspended"
    | "pending";


export interface AdminOrganization {

    id:string;

    name:string;

    slug:string;

    ownerName:string;

    ownerEmail:string;

    memberCount:number;

    deviceCount:number;

    status:OrganizationStatus;

    createdAt:string;

}


export interface AdminUser {

    id:string;

    name:string;

    email:string;

    organizationId:string | null;

    organizationName:string;

    role:"admin" | "staff";

    organizationRole?:string;

    status:"active" | "suspended";

    lastLoginAt?:string;

    createdAt:string;

}


export type GlobalDeviceStatus = "online" | "offline";

export interface GlobalDeviceActivity {
    id:string;
    name:string;
    organization:{id:string;name:string};
    status:GlobalDeviceStatus;
    lastActivity:string|null;
}

export interface GlobalDeviceSummary extends GlobalDeviceActivity {
    identifier:string|null;
    type:string;
    assignedStaffCount:number;
}

export interface GlobalOrganizationSummary {
    id:string;
    name:string;
    totalDevices:number;
    onlineDevices:number;
    offlineDevices:number;
    staffCount:number;
    lastActivity:string|null;
}

export interface GlobalOperationsOverview {
    devices:{total:number;online:number;offline:number};
    operationalWindow:{hours:number;errors:number;crashes:number;failedFirmwareDeployments:number;failedWebhookDeliveries:number};
    organizations:{total:number;withDevices:number;items:GlobalOrganizationSummary[]};
    filters:{organizations:Array<{id:string;name:string}>};
    devicePreview:GlobalDeviceSummary[];
    recentDevices:GlobalDeviceActivity[];
    offlineDevices:GlobalDeviceActivity[];
}

export interface GlobalOperationsFilters {
    organization_id?:string;
    status?:GlobalDeviceStatus;
    search?:string;
}

export type AdminGlobalDeviceSort = "name"|"identifier"|"status"|"last_seen"|"created_at"|"organization";

export interface AdminGlobalDevice {
    id:string;
    name:string;
    identifier:string|null;
    status:GlobalDeviceStatus;
    lastActivity:string|null;
    createdAt:string;
    organization:{id:string;name:string};
    creator:{id:string;name:string}|null;
    template:{id:string;name:string}|null;
    assignedStaffCount:number;
    type:string;
    protocol:string;
    location:{id:string;name:string;lifecycle:"active"|"disabled"|"archived";isAssignable?:boolean}|null;
    macAddress:string|null;
    battery:number|null;
    firmwareVersion:string|null;
    isMonitored?:boolean;
}

export interface MonitoredDevice {id:string;name:string;identifier:string|null;status:GlobalDeviceStatus;lastActivity:string|null;organization:{id:string;name:string};assignedStaffCount:number}
export interface AdminMonitoredDashboard {summary:{total:number;online:number;offline:number};devices:MonitoredDevice[];displayLimit:number}

export interface AdminGlobalDeviceAssignment {
    id:string;
      accessLevel:DeviceAccessLevel;
    staff:{id:string;name:string;email:string};
}

export interface AdminGlobalDeviceDetail {
    device:AdminGlobalDevice;
    assignments:AdminGlobalDeviceAssignment[];
}

export interface AdminGlobalDeviceListResponse {
    data:AdminGlobalDevice[];
    meta:{current_page:number;from:number|null;last_page:number;per_page:number;to:number|null;total:number};
}

export interface AdminGlobalDeviceFilters {
    search?:string;
    organization_id?:string;
    status?:GlobalDeviceStatus;
    sort?:AdminGlobalDeviceSort;
    direction?:"asc"|"desc";
    per_page?:25|50|100;
    page?:number;
    recent?:boolean;
}

export interface AdminGlobalDeviceCreate {organization_id:string;name:string;type:string;serialNumber:string;protocol:string;location_id?:string|null;macAddress?:string;template_id?:string}

export interface AdminGlobalDeviceUpdate {
    name?:string;
    type?:string;
    protocol?:string;
    location?:string|null;
    macAddress?:string|null;
}


export type DeviceAccessLevel = "viewer" | "full_access";


export interface AdminDeviceOption {

    id:string;

    name:string;

    organizationId:string | null;

    organizationName:string;

    status:string;

}


export interface DeviceAccessAssignment {

    id:string;

    accessLevel:DeviceAccessLevel;
    capabilities:{canUpgrade:boolean;canDowngrade:boolean;canRevoke:boolean};

    device:{

        id:string;

        name:string;

        identifier:string|null;

        organizationName:string;

    };

    user:{

        id:string;

        name:string;

        email:string;

        organizationName:string;

        role:"admin" | "staff";

    };

    assignedBy:{

        id:string;

        name:string;

    } | null;

    createdAt:string;

    updatedAt:string;

}

export interface DeviceAccessListResponse {data:DeviceAccessAssignment[];meta:{current_page:number;last_page:number;per_page:number;total:number}}
export interface AdminStaffOption {id:string;name:string;email:string;organizationId:string;organizationName:string}
export interface AdminUserDetail extends AdminUser {assignedDevicesCount:number}
