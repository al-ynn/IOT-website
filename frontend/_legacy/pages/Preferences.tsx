import { Card, CardContent, CardHeader } from "../../components/ui";
import { SettingsHeader, SettingsLayout } from "../../components/settings/layout/SettingsLayout";
import ThemeSettings from "../../components/settings/preferences/ThemeSettings";
export default function Preferences(){return <SettingsLayout><SettingsHeader title="Preferences" description="Personalize how the application looks on this device."/><ThemeSettings/><Card><CardHeader title="Regional preferences"/><CardContent><p className="text-sm text-[var(--ds-text-muted)]">Personal language and timezone preferences are not currently supported. Organization-wide regional settings remain under Organization settings.</p></CardContent></Card></SettingsLayout>}
