import api from "./api";import type {Permission} from "../types/permission";export async function getPermissions(){const response=await api.get<Permission[]>("/permissions");return response.data}
