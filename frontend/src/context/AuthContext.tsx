import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import type { User } from "../types/auth";
import { getCurrentUser, login as loginRequest, logout as logoutRequest, register as registerRequest } from "../services/auth.service";
import { getToken, removeToken } from "../services/storage.service";

interface AuthContextType { user:User|null; loading:boolean; login(email:string,password:string):Promise<User>; register(data:{name:string;email:string;password:string}):Promise<User>; logout():Promise<void>; refresh():Promise<void>; }
const AuthContext=createContext<AuthContextType|undefined>(undefined);
export function AuthProvider({children}:{children:ReactNode}) { const [user,setUser]=useState<User|null>(null);const [loading,setLoading]=useState(true);const refresh=useCallback(async()=>{if(!getToken()){setUser(null);setLoading(false);return;}try{setUser(await getCurrentUser());}catch{removeToken();setUser(null);}finally{setLoading(false);}},[]);useEffect(()=>{const timer=window.setTimeout(()=>void refresh(),0);return()=>window.clearTimeout(timer);},[refresh]);const login=useCallback(async(email:string,password:string)=>{const s=await loginRequest(email,password);setUser(s.user);return s.user;},[]);const register=useCallback(async(data:{name:string;email:string;password:string})=>{const s=await registerRequest(data);setUser(s.user);return s.user;},[]);const logout=useCallback(async()=>{try{await logoutRequest();}finally{removeToken();setUser(null);}},[]);const value=useMemo(()=>({user,loading,login,register,logout,refresh}),[user,loading,login,register,logout,refresh]);return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;}
// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(){const c=useContext(AuthContext);if(!c)throw new Error("AuthProvider missing");return c;}
