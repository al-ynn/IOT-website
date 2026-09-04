import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import type { Organization } from "../types/organization";
import { getOrganization } from "../services/organization.service";
import { useAuth } from "./AuthContext";
interface Context { organization:Organization|null; loading:boolean; error:string|null; refresh():Promise<void>; }
const OrganizationContext=createContext<Context|undefined>(undefined);
export function OrganizationProvider({children}:{children:ReactNode}) {const {user,loading:authLoading}=useAuth();const [organization,setOrganization]=useState<Organization|null>(null);const [loading,setLoading]=useState(false);const [error,setError]=useState<string|null>(null);const [loadedUserId,setLoadedUserId]=useState<string|null>(null);const refresh=useCallback(async()=>{if(!user){setOrganization(null);setLoadedUserId(null);return;}setLoading(true);setOrganization(null);setLoadedUserId(null);setError(null);try{setOrganization(await getOrganization());setLoadedUserId(user.id);}catch{setOrganization(null);setError("Unable to load organization.");}finally{setLoading(false);}},[user]);useEffect(()=>{if(authLoading)return;const timer=window.setTimeout(()=>void refresh(),0);return()=>window.clearTimeout(timer);},[authLoading,refresh]);const unresolved=!!user&&loadedUserId!==user.id&&!error;const value=useMemo(()=>({organization,loading:authLoading||loading||unresolved,error,refresh}),[organization,authLoading,loading,unresolved,error,refresh]);return <OrganizationContext.Provider value={value}>{children}</OrganizationContext.Provider>;}
// eslint-disable-next-line react-refresh/only-export-components
export function useOrganization(){const c=useContext(OrganizationContext);if(!c)throw new Error("OrganizationProvider missing");return c;}
