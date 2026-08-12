import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import { getPermissions } from "../services/permission.service";
import { useAuth } from "./AuthContext";
interface Context { permissions:string[]; loading:boolean; can(permission:string):boolean; hasPermission(permission:string):boolean; refresh():Promise<void>; }
const PermissionContext=createContext<Context|undefined>(undefined);
export function PermissionProvider({children}:{children:ReactNode}) {const {user,loading:authLoading}=useAuth();const [permissions,setPermissions]=useState<string[]>([]);const [loading,setLoading]=useState(false);const refresh=useCallback(async()=>{if(!user){setPermissions([]);return;}setLoading(true);try{const data=await getPermissions();setPermissions(data.map(p=>p.name));}finally{setLoading(false);}},[user]);useEffect(()=>{if(!authLoading)void refresh();},[authLoading,refresh]);const can=useCallback((p:string)=>permissions.includes("*")||permissions.includes(p),[permissions]);const value=useMemo(()=>({permissions,loading:authLoading||loading,can,hasPermission:can,refresh}),[permissions,authLoading,loading,can,refresh]);return <PermissionContext.Provider value={value}>{children}</PermissionContext.Provider>;}
// eslint-disable-next-line react-refresh/only-export-components
export function usePermissions(){const c=useContext(PermissionContext);if(!c)throw new Error("PermissionProvider missing");return c;}
