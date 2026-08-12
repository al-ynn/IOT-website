import type { ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { HelmetProvider } from "react-helmet-async";
import { AuthProvider, useAuth } from "../context/AuthContext";
import { OrganizationProvider } from "../context/OrganizationContext";
import { PermissionProvider } from "../context/PermissionContext";
import { BillingProvider } from "../context/BillingContext";
import useTelemetry from "../hooks/useTelemetry";
import useDeviceHealth from "../hooks/useDeviceHealth";
import { ThemeProvider } from "../design-system/themes";

const queryClient=new QueryClient({defaultOptions:{queries:{retry:1,refetchOnWindowFocus:false}}});
function RealtimeInitializers(){const {user}=useAuth();useTelemetry(Boolean(user));useDeviceHealth(Boolean(user));return null;}
export default function Providers({children}:{children:ReactNode}){return <ThemeProvider><AuthProvider><OrganizationProvider><PermissionProvider><BillingProvider><RealtimeInitializers/><HelmetProvider><QueryClientProvider client={queryClient}>{children}</QueryClientProvider></HelmetProvider></BillingProvider></PermissionProvider></OrganizationProvider></AuthProvider></ThemeProvider>;}
