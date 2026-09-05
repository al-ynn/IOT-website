import {
  Activity,
  BarChart3,
  BookOpen,
  Boxes,
  ClipboardCheck,
  CircleGauge,
  Cpu,
  FileText,
  Gauge,
  History,
  LayoutDashboard,
  MapPin,
  PlugZap,
  Settings,
  Users,
  Workflow,
  type LucideIcon,
} from "lucide-react";
import { matchPath } from "react-router-dom";

export type PrimaryNavigationSection =
  | "workspace"
  | "operations"
  | "connections"
  | "collaboration"
  | "administration";
export interface PrimaryNavigationItem {
  id: string;
  section: PrimaryNavigationSection;
  label: string;
  path: string;
  icon: LucideIcon;
  matches: string[];
  permission?: string;
  adminOnly?: boolean;
}
export const primaryNavigationSectionLabels: Record<
  PrimaryNavigationSection,
  string
> = {
  workspace: "Workspace",
  operations: "Build & operations",
  connections: "Connections",
  collaboration: "Collaboration",
  administration: "Administration",
};

export const primaryNavigationItems: PrimaryNavigationItem[] = [
  {
    id: "dashboard",
    section: "workspace",
    label: "Dashboard",
    path: "/app/dashboard",
    icon: LayoutDashboard,
    matches: ["/app/dashboard", "/app/dashboard/*"],
    permission: "dashboard.view",
  },
  {
    id: "devices",
    section: "workspace",
    label: "Devices",
    path: "/app/devices",
    icon: Activity,
    matches: [
      "/app/devices",
      "/app/devices/*",
      "/app/fleet",
      "/app/debug/*",
      "/app/developer/credentials",
    ],
    permission: "device.view",
  },
  {
    id: "analytics",
    section: "workspace",
    label: "Analytics",
    path: "/app/analytics",
    icon: BarChart3,
    matches: ["/app/analytics", "/app/analytics/*"],
    permission: "analytics.view",
  },
  {
    id: "templates",
    section: "operations",
    label: "Templates",
    path: "/app/developer/templates",
    icon: BookOpen,
    matches: ["/app/developer/templates", "/app/developer/templates/*"],
    permission: "device.view",
  },
  {
    id: "automations",
    section: "operations",
    label: "Automations",
    path: "/app/automations",
    icon: Workflow,
    matches: ["/app/automations", "/app/automations/*"],
    permission: "automation.view",
  },
  {
    id: "reports",
    section: "operations",
    label: "Reports",
    path: "/app/reports",
    icon: FileText,
    matches: ["/app/reports", "/app/reports/*"],
  },
  {
    id: "locations",
    section: "operations",
    label: "Locations",
    path: "/app/locations",
    icon: MapPin,
    matches: ["/app/locations", "/app/locations/*"],
    permission: "device.view",
  },
  {
    id: "firmware",
    section: "operations",
    label: "Firmware",
    path: "/app/developer/firmware",
    icon: Cpu,
    matches: ["/app/developer/firmware", "/app/developer/firmware/*"],
    permission: "device.view",
  },
  {
    id: "integrations",
    section: "connections",
    label: "Integrations",
    path: "/app/developer/integrations",
    icon: PlugZap,
    matches: ["/app/developer/integrations", "/app/developer/integrations/*"],
    permission: "device.manage",
  },
  {
    id: "webhooks",
    section: "connections",
    label: "Webhooks",
    path: "/app/developer/webhooks",
    icon: Workflow,
    matches: ["/app/developer/webhooks", "/app/developer/webhooks/*"],
    permission: "device.manage",
  },
  {
    id: "collaboration-overview",
    section: "collaboration",
    label: "Collaboration",
    path: "/app/collaboration",
    icon: CircleGauge,
    matches: ["/app/collaboration"],
  },
  {
    id: "my-work",
    section: "collaboration",
    label: "My Work",
    path: "/app/my-work",
    icon: ClipboardCheck,
    matches: ["/app/my-work"],
    permission: "device.view",
  },
  {
    id: "shared-with-me",
    section: "collaboration",
    label: "Shared With Me",
    path: "/app/shared-with-me",
    icon: Users,
    matches: ["/app/shared-with-me"],
  },
  {
    id: "changes",
    section: "collaboration",
    label: "Changes",
    path: "/app/changes",
    icon: History,
    matches: ["/app/changes"],
    permission: "device.view",
  },
  {
    id: "activity",
    section: "collaboration",
    label: "Activity",
    path: "/app/activity",
    icon: Activity,
    matches: ["/app/activity"],
  },
  {
    id: "admin-overview",
    section: "administration",
    label: "Overview",
    path: "/admin/overview",
    icon: Gauge,
    matches: ["/admin/overview"],
    adminOnly: true,
  },
  {
    id: "admin-reviews",
    section: "administration",
    label: "Reviews",
    path: "/admin/reviews",
    icon: ClipboardCheck,
    matches: [
      "/admin/reviews",
      "/admin/reviews/*",
      "/admin/template-submissions/*",
      "/admin/dashboard-submissions/*",
    ],
    adminOnly: true,
  },
  {
    id: "admin-resources",
    section: "administration",
    label: "Resources",
    path: "/admin/resources",
    icon: Boxes,
    matches: [
      "/admin/resources",
      "/admin/resources/*",
      "/admin/recently-created",
      "/admin/recently-updated",
      "/admin/disabled-resources",
      "/admin/needs-attention",
      "/admin/devices",
      "/admin/devices/*",
      "/admin/locations",
      "/admin/locations/*",
      "/admin/device-access",
      "/admin/access-requests",
    ],
    adminOnly: true,
  },
  {
    id: "admin-settings",
    section: "administration",
    label: "System Settings",
    path: "/admin/system",
    icon: Settings,
    matches: [
      "/admin/system",
      "/admin/users",
      "/admin/users/*",
      "/admin/organizations",
      "/admin/organizations/*",
    ],
    adminOnly: true,
  },
];

const normalizePath = (pathname: string) =>
  pathname.length > 1
    ? pathname.split(/[?#]/, 1)[0].replace(/\/+$/, "")
    : pathname;
export function routeMatches(pathname: string, patterns: string[]): boolean {
  return patterns.some(
    (pattern) =>
      matchPath({ path: pattern, end: true }, normalizePath(pathname)) !== null,
  );
}
export function visiblePrimaryNavigationItems(
  isAdmin: boolean,
  can: (permission: string) => boolean,
): PrimaryNavigationItem[] {
  return primaryNavigationItems.filter(
    (entry) =>
      (!entry.adminOnly || isAdmin) &&
      (!entry.permission || can(entry.permission)),
  );
}
export function searchableNavigationItems(
  isAdmin: boolean,
  can: (permission: string) => boolean,
): PrimaryNavigationItem[] {
  return visiblePrimaryNavigationItems(isAdmin, can);
}

export const navigationLabels: Record<string, string> = {
  app: "Operations",
  activity: "Activity",
  admin: "Administration",
  dashboard: "Dashboard",
  devices: "Devices",
  analytics: "Analytics",
  automations: "Automations",
  reports: "Reports",
  fleet: "Fleet",
  locations: "Locations",
  developer: "Developer Tools",
  templates: "Device Templates",
  firmware: "Firmware / OTA",
  credentials: "Device Credentials",
  integrations: "Integrations",
  webhooks: "Webhooks",
  debug: "Debugging",
  provisioning: "Provisioning Sessions",
  events: "Operational Events",
  crashes: "Crash Reports",
  overview: "Global Operations",
  resources: "Resources",
  reviews: "Review Center",
  "my-work": "My Work",
  "needs-attention": "Needs Attention",
  "disabled-resources": "Disabled Resources",
  "recently-created": "Recently Created",
  "recently-updated": "Recently Updated",
  users: "Users / Staff",
  organizations: "Organizations",
  system: "System Settings",
  "device-access": "Device Access",
};
