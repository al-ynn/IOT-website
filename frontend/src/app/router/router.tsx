import { createBrowserRouter } from "react-router-dom";
import { Suspense, type ReactElement } from "react";
import Home from "../../pages/Home";
import Login from "../../pages/auth/Login";
import Register from "../../pages/auth/Register";
import ForgotPassword from "../../pages/auth/ForgotPassword";
import ResetPassword from "../../pages/auth/ResetPassword";
import VerifyEmail from "../../pages/auth/VerifyEmail";
import AcceptInvitation from "../../pages/auth/AcceptInvitation";
import Unauthorized from "../../pages/auth/Unauthorized";
import PublicLayout from "../../layouts/PublicLayout";
import Features from "../../pages/public/Features";
import Solutions from "../../pages/public/Solutions";
import Developers from "../../pages/public/Developers";
import Enterprise from "../../pages/public/Enterprise";
import Docs from "../../pages/public/Docs";
import Contact from "../../pages/public/Contact";
import About from "../../pages/public/About";
import ProtectedRoute from "../../guards/ProtectedRoute";
import CustomerLayout from "../../layouts/CustomerLayout";
import AdminLayout from "../../layouts/AdminLayout";
import {AddDevice,AdminBilling,AdminDashboard,AdminOrganizations,AdminSystem,AdminUsers,Analytics,AutomationDetail,AutomationExecutionDetail,AutomationLogs,Automations,AutomationTemplates,BillingPortal,CheckoutCancelled,CheckoutSuccess,CreateAutomation,CreateDashboard,Dashboard,DashboardBuilder,DashboardDetail,DashboardPreview,Dashboards,DashboardTemplates,DeviceAnalytics,DeviceCenter,Devices,MetricAnalytics,Subscription,TelemetryCenter,TelemetryMetric} from "./lazy-pages";
import DeferredFeature from "../../pages/customer/DeferredFeature";
import FeatureGate from "../../components/billing/FeatureGate";
import UpgradePrompt from "../../components/billing/UpgradePrompt";
import Organization from "../../pages/customer/Organization";
import Members from "../../pages/customer/Members";
import Invitations from "../../pages/customer/Invitations";
import OrganizationRoles from "../../pages/customer/OrganizationRoles";
import OrganizationSettings from "../../pages/customer/OrganizationSettings";
import Profile from "../../pages/customer/Profile";
import SettingsHome from "../../pages/customer/SettingsHome";
import SecuritySettings from "../../pages/customer/SecuritySettings";
import Preferences from "../../pages/customer/Preferences";
import NotificationPreferences from "../../pages/customer/NotificationPreferences";
import Notifications from "../../pages/customer/Notifications";
import Activity from "../../pages/customer/Activity";
import Reports from "../../pages/customer/Reports";
import CreateReport from "../../pages/customer/CreateReport";
import ReportDetail from "../../pages/customer/ReportDetail";
import ExportHistory from "../../pages/customer/ExportHistory";
import Pricing from "../../pages/billing/Pricing";
import Checkout from "../../components/billing/Checkout";
import NotFound from "../../pages/NotFound";

const routeFallback=<div role="status" className="flex min-h-48 items-center justify-center text-sm text-[var(--ds-text-muted)]">Loading page...</div>;
const customer=(page:ReactElement, options?:{permission?:string;roles?:string[];requireOrganization?:boolean})=><ProtectedRoute {...options}><CustomerLayout><Suspense fallback={routeFallback}>{page}</Suspense></CustomerLayout></ProtectedRoute>;
const admin=(page:ReactElement)=><ProtectedRoute platformAdmin requireOrganization={false}><AdminLayout><Suspense fallback={routeFallback}>{page}</Suspense></AdminLayout></ProtectedRoute>;
const deferred=(name:string)=>customer(<DeferredFeature name={name}/>);
const automation=(page:ReactElement,permission="automation.view")=>customer(<FeatureGate feature="automation.basic" fallback={<UpgradePrompt title="Upgrade required" description="Your current plan does not include Automation."/>}>{page}</FeatureGate>,{permission});
const analytics=(page:ReactElement)=>customer(<FeatureGate feature="analytics.advanced" fallback={<UpgradePrompt title="Upgrade required" description="Your current plan does not include Advanced Analytics."/>}>{page}</FeatureGate>,{permission:"analytics.view"});

export default createBrowserRouter([
 {element:<PublicLayout/>,children:[
  {path:"/",element:<Home/>},{path:"/features",element:<Features/>},{path:"/solutions",element:<Solutions/>},{path:"/developers",element:<Developers/>},{path:"/enterprise",element:<Enterprise/>},{path:"/pricing",element:<Pricing/>},{path:"/docs",element:<Docs/>},{path:"/contact",element:<Contact/>},{path:"/about",element:<About/>},
  {path:"/login",element:<Login/>},{path:"/register",element:<Register/>},{path:"/forgot-password",element:<ForgotPassword/>},{path:"/reset-password",element:<ResetPassword/>},{path:"/verify-email",element:<VerifyEmail/>},{path:"/invite/:token",element:<AcceptInvitation/>},{path:"/unauthorized",element:<Unauthorized/>},
 ]},
 {path:"/app/dashboard",element:customer(<Dashboard/>,{permission:"dashboard.view"})},{path:"/app/dashboards",element:customer(<Dashboards/>,{permission:"dashboard.view"})},{path:"/app/dashboard/templates",element:customer(<DashboardTemplates/>,{permission:"dashboard.view"})},{path:"/app/dashboard/preview",element:customer(<DashboardPreview/>,{permission:"dashboard.view"})},{path:"/app/dashboard/:id",element:customer(<DashboardDetail/>,{permission:"dashboard.view"})},{path:"/app/dashboard-builder",element:customer(<DashboardBuilder/>,{permission:"dashboard.create"})},{path:"/app/create-dashboard",element:customer(<CreateDashboard/>,{permission:"dashboard.create"})},
 {path:"/app/devices",element:customer(<Devices/>,{permission:"device.view"})},{path:"/app/devices/add",element:customer(<AddDevice/>,{permission:"device.manage"})},{path:"/app/devices/claim",element:deferred("Device claiming")},{path:"/app/devices/:id",element:customer(<DeviceCenter/>,{permission:"device.view"})},
 {path:"/app/telemetry",element:analytics(<TelemetryCenter/>)},{path:"/app/telemetry/:metric",element:analytics(<TelemetryMetric/>)},{path:"/app/settings",element:customer(<SettingsHome/>)},
 {path:"/app/notifications",element:customer(<Notifications/>)},{path:"/app/activity",element:automation(<Activity/>)},
 {path:"/app/firmware",element:deferred("Firmware management")},{path:"/app/device-groups",element:deferred("Device groups")},{path:"/app/automations",element:automation(<Automations/>)},{path:"/app/automations/create",element:automation(<CreateAutomation/>,"automation.create")},{path:"/app/automations/:id",element:automation(<AutomationDetail/>)},{path:"/app/automation-logs",element:automation(<AutomationLogs/>)},{path:"/app/automation-logs/:id",element:automation(<AutomationExecutionDetail/>)},{path:"/app/automation-templates",element:automation(<AutomationTemplates/>)},{path:"/app/workflows",element:deferred("Workflows")},
 {path:"/app/analytics",element:analytics(<Analytics/>)},{path:"/app/analytics/device/:id",element:analytics(<DeviceAnalytics/>)},{path:"/app/analytics/metric/:metric",element:analytics(<MetricAnalytics/>)},{path:"/app/reports",element:customer(<Reports/>)},{path:"/app/reports/create",element:customer(<CreateReport/>)},{path:"/app/reports/:id",element:customer(<ReportDetail/>)},{path:"/app/exports",element:customer(<ExportHistory/>)},
 {path:"/app/organization",element:customer(<Organization/>,{requireOrganization:false})},{path:"/app/members",element:customer(<Members/>)},{path:"/app/invitations",element:customer(<Invitations/>,{permission:"members.manage"})},{path:"/app/roles",element:customer(<OrganizationRoles/>)},{path:"/app/settings/organization",element:customer(<OrganizationSettings/>)},{path:"/app/profile",element:customer(<Profile/>)},{path:"/app/security",element:customer(<SecuritySettings/>)},{path:"/app/preferences",element:customer(<Preferences/>)},{path:"/app/notifications/settings",element:customer(<NotificationPreferences/>)},{path:"/app/audit-logs",element:deferred("Audit logs")},
 {path:"/app/billing",element:customer(<BillingPortal/>)},{path:"/app/subscription",element:customer(<Subscription/>)},{path:"/app/checkout",element:customer(<Checkout/>)},{path:"/app/checkout/success",element:customer(<CheckoutSuccess/>)},{path:"/app/checkout/cancelled",element:customer(<CheckoutCancelled/>)},
 {path:"/admin/dashboard",element:admin(<AdminDashboard/>)},{path:"/admin/organizations",element:admin(<AdminOrganizations/>)},{path:"/admin/users",element:admin(<AdminUsers/>)},{path:"/admin/billing",element:admin(<AdminBilling/>)},{path:"/admin/system",element:admin(<AdminSystem/>)},
 {path:"/app/*",element:customer(<NotFound/>)},{path:"/admin/*",element:admin(<NotFound/>)},{path:"*",element:<NotFound/>},
]);
