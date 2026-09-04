import { Card, CardContent, CardHeader } from "../../components/ui";
import { SettingsHeader, SettingsLayout } from "../../components/settings/layout/SettingsLayout";
import PasswordForm from "../../components/settings/security/PasswordForm";
export default function SecuritySettings(){return <SettingsLayout><SettingsHeader title="Security" description="Protect access to your IoT Platform account."/><PasswordForm/><Card><CardHeader title="Additional security"/><CardContent><p className="text-sm text-[var(--ds-text-muted)]">Two-factor authentication and individual session management are not currently available. Your password change revokes every existing API session.</p></CardContent></Card></SettingsLayout>}
