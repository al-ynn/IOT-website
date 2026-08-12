import type { ReactNode } from "react";
import { Navigate, useLocation } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import { useOrganization } from "../hooks/useOrganization";
import usePermission from "../hooks/usePermission";

interface Props { children:ReactNode; permission?:string; roles?:string[]; platformAdmin?:boolean; requireOrganization?:boolean; }
export default function ProtectedRoute({children,permission,roles,platformAdmin=false,requireOrganization=true}:Props){
 const location=useLocation();const {user,loading:authLoading}=useAuth();const {organization,loading:orgLoading}=useOrganization();const {can,loading:permissionLoading}=usePermission();
 if(authLoading||orgLoading||permissionLoading)return <div className="p-6 text-gray-400">Loading…</div>;
 if(!user)return <Navigate to="/login" replace state={{from:location}}/>;
 if(platformAdmin&&user.platformRole!=="platform_admin")return <Navigate to="/unauthorized" replace/>;
 if(requireOrganization&&!platformAdmin&&!organization)return <Navigate to="/app/organization" replace/>;
 if(roles&&!roles.includes(user.role))return <Navigate to="/unauthorized" replace/>;
 if(permission&&!can(permission))return <Navigate to="/unauthorized" replace/>;
 return <>{children}</>;
}
