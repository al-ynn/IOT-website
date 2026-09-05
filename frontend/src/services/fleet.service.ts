import api from "./api";import type {FleetFilters,FleetListResponse} from "../types/fleet";
export async function listFleetDevices(filters:FleetFilters={}){return (await api.get<FleetListResponse>("/fleet/devices",{params:filters})).data;}
