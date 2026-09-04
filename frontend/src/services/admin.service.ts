import api from "./api";


import type {

    AdminDeviceOption,
    GlobalOperationsFilters,
    GlobalOperationsOverview,
    AdminGlobalDeviceDetail,
    AdminGlobalDeviceFilters,
    AdminGlobalDeviceListResponse,
    AdminGlobalDeviceUpdate,
    AdminGlobalDeviceCreate,
    AdminMonitoredDashboard,

    AdminOrganization,

    DeviceAccessAssignment, DeviceAccessListResponse, AdminStaffOption, AdminUserDetail,
    AdminUser

}

from "../types/admin";
import type{AdminReviewItem,AdminReviewPage,AdminReviewState,AdminReviewSummary,ReviewCenterResourceType}from"../types/admin-review";
import type{AdminRecentFilters,AdminRecentPage}from"../types/admin-recent";
import type{AdminDisabledFilters,AdminDisabledPage,AdminDisabledSummary}from"../types/admin-disabled";





export async function getGlobalOperationsOverview(params?:GlobalOperationsFilters){
    const response = await api.get<GlobalOperationsOverview>("/admin/operations/overview",{params});
    return response.data;
}

export async function listGlobalDevices(params?:AdminGlobalDeviceFilters){
    const response=await api.get<AdminGlobalDeviceListResponse>("/admin/devices",{params});
    return response.data;
}

export async function getGlobalDevice(id:string){
    const response=await api.get<{data:AdminGlobalDeviceDetail}>(`/admin/devices/${id}`);
    return response.data.data;
}

export async function updateGlobalDevice(id:string,payload:AdminGlobalDeviceUpdate){
    const response=await api.patch<{data:AdminGlobalDeviceDetail}>(`/admin/devices/${id}`,payload);
    return response.data.data;
}

export async function createGlobalDevice(payload:AdminGlobalDeviceCreate){
    const response=await api.post<{data:AdminGlobalDeviceDetail}>("/admin/devices",payload);
    return response.data.data;
}





export async function getAdminOrganizations(){



    const response =

        await api.get<AdminOrganization[]>(

            "/admin/organizations"

        );



    return response.data;

}





export async function updateOrganizationStatus(

    id:string,

    status:"active" | "suspended"

){



    const response =

        await api.patch(

            `/admin/organizations/${id}/status`,

            {

                status

            }

        );



    return response.data;

}





export async function getAdminUsers(){



    const response =

        await api.get<AdminUser[]>(

            "/admin/users"

        );



    return response.data;

}

export async function createAdminUser(data:{
    name:string;
    email:string;
    password:string;
    password_confirmation:string;
    role:"admin"|"staff";
    organization_id?:string;
}){
    const response = await api.post<AdminUser>("/admin/users", data);
    return response.data;
}





export async function updateUserStatus(

    id:string,

    status:"active" | "suspended"

){



    const response =

        await api.patch(

            `/admin/users/${id}/status`,

            {

                status

            }

        );



    return response.data;

}

export async function getAdminDeviceOptions(params?:Record<string,string|undefined>){
    const response = await api.get<AdminDeviceOption[]>("/admin/devices/options",{params});
    return response.data;
}

export async function listDeviceAccessAssignments(params?:Record<string,string|number|undefined>){
    const response = await api.get<DeviceAccessListResponse>("/admin/device-access",{params});
    return response.data;
}

export async function getAdminStaffOptions(params?:Record<string,string|undefined>){const response=await api.get<AdminStaffOption[]>("/admin/users/options",{params});return response.data;}
export async function getAdminUser(id:string){const response=await api.get<AdminUserDetail>(`/admin/users/${id}`);return response.data;}
export async function getMonitoredDashboard(){const response=await api.get<AdminMonitoredDashboard>("/admin/monitored-devices");return response.data;}
export async function addMonitoredDevice(deviceId:string){const response=await api.post<{monitored:boolean}>("/admin/monitored-devices",{device_id:deviceId});return response.data;}
export async function removeMonitoredDevice(deviceId:string){await api.delete(`/admin/monitored-devices/${deviceId}`);}

export async function createDeviceAccessAssignment(data:{
    device_id:string | number;
    user_id:string | number;
    access_level:"viewer" | "full_access";
}){
    const response = await api.post<DeviceAccessAssignment>("/admin/device-access", data);
    return response.data;
}

export async function updateDeviceAccessAssignment(id:string, data:{access_level:"viewer" | "full_access"}){
    const response = await api.patch<DeviceAccessAssignment>(`/admin/device-access/${id}`, data);
    return response.data;
}

export async function deleteDeviceAccessAssignment(id:string){
    await api.delete(`/admin/device-access/${id}`);
}

export async function getDeviceAccessForDevice(deviceId:string | number){
    const response = await api.get<DeviceAccessAssignment[]>(`/admin/devices/${deviceId}/access`);
    return response.data;
}

export async function getDeviceAccessForUser(userId:string | number){
    const response = await api.get<DeviceAccessAssignment[]>(`/admin/users/${userId}/device-access`);
    return response.data;
}
export async function getAdminReviewSummary(params?:{search?:string;organization_id?:string;resource_type?:ReviewCenterResourceType}){const response=await api.get<{data:AdminReviewSummary}>("/admin/reviews/summary",{params});return response.data.data;}
export async function listAdminReviews(params:{state?:AdminReviewState;resource_type?:ReviewCenterResourceType;search?:string;sort?:"oldest"|"newest";page?:number}){const response=await api.get<AdminReviewPage>("/admin/reviews",{params});return response.data;}
const reviewSegments:Record<ReviewCenterResourceType,string>={device_template:"template-publication-submissions",dashboard:"dashboard-publication-submissions",automation:"automation-publication-submissions",report:"report-publication-submissions",webhook:"webhook-activation-submissions",firmware:"firmware-submissions"};
function reviewActionBase(id:string,type:ReviewCenterResourceType){return `/admin/${reviewSegments[type]}/${id}`;}
export async function claimAdminReview(id:string,type:ReviewCenterResourceType){await api.post(`${reviewActionBase(id,type)}/claim`);}
export async function releaseAdminReview(id:string,type:ReviewCenterResourceType){await api.post(`${reviewActionBase(id,type)}/release`);}
export async function takeoverAdminReview(id:string,type:ReviewCenterResourceType){await api.post(`${reviewActionBase(id,type)}/takeover`,{confirm:true});}
export async function getAdminReviewSubmission(id:string,type:ReviewCenterResourceType){const response=await api.get<{data:AdminReviewItem}>(`/admin/reviews/${type}/${id}`);return response.data.data;}
export async function approveAdminReview(id:string,type:ReviewCenterResourceType,confirmRevision=false){const payload=type==="webhook"?{confirm_active_change:confirmRevision}:type==="firmware"?{}:{confirm_older_revision:confirmRevision};await api.post(`${reviewActionBase(id,type)}/approve`,payload);}
export async function requestAdminReviewChanges(id:string,type:ReviewCenterResourceType,note:string){await api.post(`${reviewActionBase(id,type)}/request-changes`,{note});}
export async function rejectAdminReview(id:string,type:ReviewCenterResourceType,note:string){await api.post(`${reviewActionBase(id,type)}/reject`,{note});}
export async function listAdminRecentlyCreated(params:AdminRecentFilters){const response=await api.get<AdminRecentPage>("/admin/recently-created",{params});return response.data;}
export async function listAdminRecentlyUpdated(params:AdminRecentFilters){const response=await api.get<AdminRecentPage>("/admin/recently-updated",{params});return response.data;}
export async function listAdminDisabledResources(params:AdminDisabledFilters){const response=await api.get<AdminDisabledPage>("/admin/disabled-resources",{params});return response.data;}
export async function getAdminDisabledSummary(){const response=await api.get<{data:AdminDisabledSummary}>("/admin/disabled-resources/summary");return response.data.data;}
