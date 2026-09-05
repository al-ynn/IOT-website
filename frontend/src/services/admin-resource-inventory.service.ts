import api from"./api";
import type{AdminInventoryFilters,AdminInventoryMetadata,AdminInventoryPage,AdminInventoryRow,InventoryResourceType}from"../types/admin-resource-inventory";
export async function listAdminResourceInventory(params:AdminInventoryFilters){return(await api.get<AdminInventoryPage>("/admin/resources/inventory",{params})).data}
export async function getAdminResourceInventoryMetadata(){return(await api.get<{data:AdminInventoryMetadata}>("/admin/resources/inventory/metadata")).data.data}

export async function getAdminResourceInventoryRow(type:InventoryResourceType,id:string){return(await api.get<{data:AdminInventoryRow}>(`/admin/resources/inventory/${type}/${id}`)).data.data}

export async function markAdminResourceViewed(type:InventoryResourceType,id:string){return(await api.post<{data:Pick<AdminInventoryRow,"viewState"|"firstViewedAt"|"lastViewedAt">}>(`/admin/resources/inventory/${type}/${id}/viewed`)).data.data}