import { Suspense, type ReactElement } from "react";
import { createBrowserRouter, Navigate } from "react-router-dom";
import Login from "../../pages/auth/Login";
import AcceptInvitation from "../../pages/auth/AcceptInvitation";
import Unauthorized from "../../pages/auth/Unauthorized";
import NotFound from "../../pages/NotFound";
import ProtectedRoute from "../../guards/ProtectedRoute";
import AppLayout from "../../layouts/AppLayout";
import {
  AddDevice,
  Analytics,
  AutomationDetail,
  AutomationForm,
  Automations,
  ChangesInbox,
  CollaborationActivity,
  CollaborationOverview,
  Dashboard,
  DashboardDetail,
  DashboardPublication,
  PublishedDashboards,
  PublishedDashboardDetail,
  DeviceAnalytics,
  DeviceCenter,
  DeviceCredentialsPage,
  Devices,
  DeviceTemplateDetail,
  DeviceTemplates,
  FirmwareOta,
  Fleet,
  Integrations,
  LocationDetail,
  Locations,
  MetricAnalytics,
  MyWork,
  Notifications,
  ReportDetail,
  Reports,
  ShareDashboard,
  SharedWithMe,
  Search,
  ShareDevice,
  ShareRequestDetail,
  WebhookDetail,
  Webhooks,
} from "./lazy-pages";
import {
  AdminAccessRequests,
  AdminDeviceAccess,
  AdminDisabledResources,
  AdminResourceInventory,
  AdminResourceInventoryDetail,
  AdminLocations,
  AdminNeedsAttention,
  AdminOrganizations,
  AdminRecentResources,
  AdminReviewCenter,
  AdminReviewDetail,
  AdminDashboardReviewDetail,
  AdminSystem,
  AdminUserDetail,
  AdminUsers,
  GlobalDeviceDetail,
  GlobalDevices,
  GlobalOperations,
} from "./lazy-pages";

const fallback = (
  <div
    role="status"
    className="flex min-h-48 items-center justify-center text-sm text-[var(--ds-text-muted)]"
  >
    Loading page...
  </div>
);
const shell = (page: ReactElement, options?: { permission?: string }) => (
  <ProtectedRoute {...options}>
    <AppLayout>
      <Suspense fallback={fallback}>{page}</Suspense>
    </AppLayout>
  </ProtectedRoute>
);
const adminShell = (page: ReactElement) => (
  <ProtectedRoute platformAdmin>
    <AppLayout>
      <Suspense fallback={fallback}>{page}</Suspense>
    </AppLayout>
  </ProtectedRoute>
);
const analytics = (page: ReactElement) =>
  shell(page, { permission: "analytics.view" });

export default createBrowserRouter([
  { path: "/", element: <Navigate to="/login" replace /> },
  { path: "/login", element: <Login /> },
  { path: "/invite/:token", element: <AcceptInvitation /> },
  { path: "/unauthorized", element: <Unauthorized /> },
  {
    path: "/app/dashboard",
    element: shell(<Dashboard />, { permission: "dashboard.view" }),
  },
  { path: "/app/notifications", element: shell(<Notifications />) },
  { path: "/app/collaboration", element: shell(<CollaborationOverview />) },
  { path: "/app/activity", element: shell(<CollaborationActivity />) },
  {
    path: "/app/changes",
    element: shell(<ChangesInbox />, { permission: "device.view" }),
  },
  {
    path: "/app/my-work",
    element: shell(<MyWork />, { permission: "device.view" }),
  },
  { path: "/app/shared-with-me", element: shell(<SharedWithMe />) },
  { path: "/app/search", element: shell(<Search />) },
  {
    path: "/app/dashboard/published",
    element: shell(<PublishedDashboards />, { permission: "dashboard.view" }),
  },
  {
    path: "/app/dashboard/published/:id",
    element: shell(<PublishedDashboardDetail />, {
      permission: "dashboard.view",
    }),
  },
  {
    path: "/app/dashboard/:id",
    element: shell(<DashboardDetail />, { permission: "dashboard.view" }),
  },
  {
    path: "/app/dashboard/:id/publication",
    element: shell(<DashboardPublication />, { permission: "dashboard.view" }),
  },
  {
    path: "/app/dashboard/:id/share",
    element: shell(<ShareDashboard />, { permission: "dashboard.view" }),
  },
  {
    path: "/app/devices",
    element: shell(<Devices />, { permission: "device.view" }),
  },
  {
    path: "/app/devices/add",
    element: shell(<AddDevice />, { permission: "device.manage" }),
  },
  {
    path: "/app/devices/:id",
    element: shell(<DeviceCenter />, { permission: "device.view" }),
  },
  {
    path: "/app/devices/:id/share",
    element: shell(<ShareDevice />, { permission: "device.view" }),
  },
  { path: "/app/shares/:id", element: shell(<ShareRequestDetail />) },
  { path: "/app/analytics", element: analytics(<Analytics />) },
  {
    path: "/app/analytics/device/:id",
    element: analytics(<DeviceAnalytics />),
  },
  {
    path: "/app/analytics/metric/:metric",
    element: analytics(<MetricAnalytics />),
  },
  { path: "/app/reports", element: shell(<Reports />) },
  { path: "/app/reports/:id", element: shell(<ReportDetail />) },
  {
    path: "/app/automations",
    element: shell(<Automations />, { permission: "automation.view" }),
  },
  {
    path: "/app/automations/new",
    element: shell(<AutomationForm />, { permission: "automation.create" }),
  },
  {
    path: "/app/automations/:id",
    element: shell(<AutomationDetail />, { permission: "automation.view" }),
  },
  {
    path: "/app/automations/:id/edit",
    element: shell(<AutomationForm />, { permission: "automation.update" }),
  },
  {
    path: "/app/fleet",
    element: shell(<Fleet />, { permission: "device.view" }),
  },
  {
    path: "/app/locations",
    element: shell(<Locations />, { permission: "device.view" }),
  },
  {
    path: "/app/locations/:id",
    element: shell(<LocationDetail />, { permission: "device.view" }),
  },
  {
    path: "/app/developer/templates",
    element: shell(<DeviceTemplates />, { permission: "device.view" }),
  },
  {
    path: "/app/developer/templates/:id",
    element: shell(<DeviceTemplateDetail />, { permission: "device.view" }),
  },
  {
    path: "/app/developer/templates/:id/:section",
    element: shell(<DeviceTemplateDetail />, { permission: "device.view" }),
  },
  {
    path: "/app/developer/firmware",
    element: shell(<FirmwareOta />, { permission: "device.view" }),
  },
  {
    path: "/app/developer/credentials",
    element: shell(<DeviceCredentialsPage />, { permission: "device.manage" }),
  },
  {
    path: "/app/developer/integrations",
    element: shell(<Integrations />, { permission: "device.manage" }),
  },
  {
    path: "/app/developer/webhooks",
    element: shell(<Webhooks />, { permission: "device.manage" }),
  },
  {
    path: "/app/developer/webhooks/:id",
    element: shell(<WebhookDetail />, { permission: "device.manage" }),
  },
  { path: "/admin/overview", element: adminShell(<GlobalOperations />) },
  { path: "/admin/devices", element: adminShell(<GlobalDevices />) },
  { path: "/admin/devices/:id", element: adminShell(<GlobalDeviceDetail />) },
  { path: "/admin/locations", element: adminShell(<AdminLocations />) },
  { path: "/admin/locations/:id", element: adminShell(<LocationDetail />) },
  { path: "/admin/device-access", element: adminShell(<AdminDeviceAccess />) },
  {
    path: "/admin/access-requests",
    element: adminShell(<AdminAccessRequests />),
  },
  { path: "/admin/reviews", element: adminShell(<AdminReviewCenter />) },
  {
    path: "/admin/needs-attention",
    element: adminShell(<AdminNeedsAttention />),
  },
  {
    path: "/admin/disabled-resources",
    element: adminShell(<AdminDisabledResources />),
  },
  { path: "/admin/resources", element: adminShell(<AdminResourceInventory />) },
  {
    path: "/admin/resources/:type/:id",
    element: adminShell(<AdminResourceInventoryDetail />),
  },
  {
    path: "/admin/recently-created",
    element: adminShell(<AdminRecentResources />),
  },
  {
    path: "/admin/recently-updated",
    element: adminShell(<AdminRecentResources />),
  },
  {
    path: "/admin/templates/:id",
    element: adminShell(<DeviceTemplateDetail />),
  },
  {
    path: "/admin/reviews/:type/:id",
    element: adminShell(<AdminReviewDetail />),
  },
  {
    path: "/admin/template-submissions/:id",
    element: adminShell(<AdminReviewDetail />),
  },
  {
    path: "/admin/dashboard-submissions/:id",
    element: adminShell(<AdminDashboardReviewDetail />),
  },
  { path: "/admin/users", element: adminShell(<AdminUsers />) },
  { path: "/admin/users/:id", element: adminShell(<AdminUserDetail />) },
  { path: "/admin/organizations", element: adminShell(<AdminOrganizations />) },
  { path: "/admin/system", element: adminShell(<AdminSystem />) },
  {
    path: "/admin/dashboard",
    element: <Navigate to="/admin/overview" replace />,
  },
  { path: "/app/*", element: shell(<NotFound />) },
  { path: "/admin/*", element: adminShell(<NotFound />) },
  { path: "*", element: <NotFound /> },
]);
