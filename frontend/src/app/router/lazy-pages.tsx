import { lazy } from "react";
export const Dashboard = lazy(() => import("../../pages/app/Dashboard"));
export const DashboardDetail = lazy(
  () => import("../../pages/app/DashboardDetail"),
);
export const PublishedDashboards = lazy(
  () => import("../../pages/app/PublishedDashboards"),
);
export const PublishedDashboardDetail = lazy(
  () => import("../../pages/app/PublishedDashboardDetail"),
);
export const DashboardPublication = lazy(
  () => import("../../pages/app/DashboardPublication"),
);
export const SharedWithMe = lazy(() => import("../../pages/app/SharedWithMe"));
export const CollaborationOverview = lazy(() => import("../../pages/app/CollaborationOverview"));
export const Search = lazy(() => import("../../pages/app/Search"));
export const Devices = lazy(() => import("../../pages/app/Devices"));
export const AddDevice = lazy(() => import("../../pages/app/AddDevice"));
export const DeviceCenter = lazy(() => import("../../pages/app/DeviceCenter"));
export const Analytics = lazy(() => import("../../pages/app/Analytics"));
export const DeviceAnalytics = lazy(
  () => import("../../pages/app/DeviceAnalytics"),
);
export const MetricAnalytics = lazy(
  () => import("../../pages/app/MetricAnalytics"),
);
export const Reports = lazy(() => import("../../pages/app/Reports"));
export const ReportDetail = lazy(() => import("../../pages/app/ReportDetail"));
export const DeviceTemplates = lazy(
  () => import("../../pages/app/DeviceTemplates"),
);
export const DeviceTemplateDetail = lazy(
  () => import("../../pages/app/DeviceTemplateLifecyclePage"),
);
export const Automations = lazy(() => import("../../pages/app/Automations"));
export const AutomationForm = lazy(
  () => import("../../pages/app/AutomationForm"),
);
export const AutomationDetail = lazy(
  () => import("../../pages/app/AutomationDetail"),
);
export const OperationalEvents = lazy(
  () => import("../../pages/app/OperationalEvents"),
);
export const Fleet = lazy(() => import("../../pages/app/Fleet"));
export const ProvisioningSessions = lazy(
  () => import("../../pages/app/ProvisioningSessions"),
);
export const ProvisioningSessionDetail = lazy(
  () => import("../../pages/app/ProvisioningSessionDetail"),
);
export const FirmwareOta = lazy(() => import("../../pages/app/FirmwareOta"));
export const DeviceCredentialsPage = lazy(
  () => import("../../pages/app/DeviceCredentialsPage"),
);
export const Integrations = lazy(() => import("../../pages/app/Integrations"));
export const Webhooks = lazy(() => import("../../pages/app/Webhooks"));
export const WebhookDetail = lazy(
  () => import("../../pages/app/WebhookDetail"),
);
export const CrashReports = lazy(() => import("../../pages/app/CrashReports"));
export const CrashReportDetail = lazy(
  () => import("../../pages/app/CrashReportDetail"),
);
export const Notifications = lazy(
  () => import("../../pages/app/Notifications"),
);
export const CollaborationActivity = lazy(
  () => import("../../pages/app/Activity"),
);
export const ShareDevice = lazy(() => import("../../pages/app/ShareDevice"));
export const ShareDashboard = lazy(
  () => import("../../pages/app/ShareDashboard"),
);
export const ShareRequestDetail = lazy(
  () => import("../../pages/app/ShareRequestDetail"),
);
export const ChangesInbox = lazy(() => import("../../pages/app/ChangesInbox"));
export const GlobalOperations = lazy(
  () => import("../../pages/admin/GlobalOperations"),
);
export const GlobalDevices = lazy(
  () => import("../../pages/admin/GlobalDevices"),
);
export const GlobalDeviceDetail = lazy(
  () => import("../../pages/admin/GlobalDeviceDetailWithParameters"),
);
export const AdminDeviceAccess = lazy(
  () => import("../../pages/admin/AdminDeviceAccess"),
);
export const AdminOrganizations = lazy(
  () => import("../../pages/admin/AdminOrganizations"),
);
export const AdminUsers = lazy(() => import("../../pages/admin/AdminUsers"));
export const AdminUserDetail = lazy(
  () => import("../../pages/admin/AdminUserDetail"),
);
export const AdminSystem = lazy(() => import("../../pages/admin/AdminSystem"));
export const AdminAccessRequests = lazy(
  () => import("../../pages/admin/AdminAccessRequests"),
);
export const AdminReviewCenter = lazy(
  () => import("../../pages/admin/AdminReviewCenter"),
);
export const AdminReviewDetail = lazy(
  () => import("../../pages/admin/AdminReviewDetail"),
);
export const AdminDashboardReviewDetail = lazy(
  () => import("../../pages/admin/AdminDashboardReviewDetail"),
);
export const AdminRecentResources = lazy(
  () => import("../../pages/admin/AdminRecentResources"),
);
export const AdminNeedsAttention = lazy(
  () => import("../../pages/admin/AdminNeedsAttention"),
);
export const AdminDisabledResources = lazy(
  () => import("../../pages/admin/AdminDisabledResources"),
);
export const AdminResourceInventory = lazy(
  () => import("../../pages/admin/AdminResourceInventory"),
);
export const AdminResourceInventoryDetail = lazy(
  () => import("../../pages/admin/AdminResourceInventoryDetail"),
);
export const MyWork = lazy(() => import("../../pages/app/MyWork"));
export const Locations = lazy(() => import("../../pages/app/Locations"));
export const LocationDetail = lazy(
  () => import("../../pages/app/LocationDetail"),
);
export const AdminLocations = lazy(
  () => import("../../pages/admin/AdminLocations"),
);
