import type { ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { HelmetProvider } from "react-helmet-async";
import { AuthProvider } from "../context/AuthContext";
import { OrganizationProvider } from "../context/OrganizationContext";
import { PermissionProvider } from "../context/PermissionContext";
import { ThemeProvider } from "../design-system/themes";
import { FeedbackProvider } from "../context/FeedbackContext";

const queryClient=new QueryClient({defaultOptions:{queries:{retry:1,refetchOnWindowFocus:false}}});
export default function Providers({children}:{children:ReactNode}){return <ThemeProvider><FeedbackProvider><AuthProvider><OrganizationProvider><PermissionProvider><HelmetProvider><QueryClientProvider client={queryClient}>{children}</QueryClientProvider></HelmetProvider></PermissionProvider></OrganizationProvider></AuthProvider></FeedbackProvider></ThemeProvider>;}
