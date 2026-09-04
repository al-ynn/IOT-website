import { Card, CardContent, CardHeader } from "../../components/ui";
import { SettingsHeader, SettingsLayout } from "../../components/settings/layout/SettingsLayout";
import NotificationSettings from "../../components/settings/notifications/NotificationSettings";
export default function NotificationPreferences(){return <SettingsLayout><SettingsHeader title="Notifications" description="Choose which account updates you want to receive."/><NotificationSettings/><Card><CardHeader title="Delivery"/><CardContent><p className="text-sm text-[var(--ds-text-muted)]">These preferences use the notification channels supported by your account. Per-device channel and quiet-hour controls are not currently available.</p></CardContent></Card></SettingsLayout>}
