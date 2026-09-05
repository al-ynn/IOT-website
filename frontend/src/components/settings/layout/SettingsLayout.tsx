import type { ReactNode } from "react";
import { NavLink } from "react-router-dom";
import { Bell, LockKeyhole, Palette, Settings, UserRound } from "lucide-react";
import { cn } from "../../../utils/cn";

const items = [
  { to: "/app/settings", label: "Overview", icon: Settings, end: true },
  { to: "/app/profile", label: "Profile", icon: UserRound },
  { to: "/app/security", label: "Security", icon: LockKeyhole },
  { to: "/app/preferences", label: "Preferences", icon: Palette },
  { to: "/app/notifications/settings", label: "Notifications", icon: Bell },
];

export function SettingsLayout({ children }: { children: ReactNode }) {
  return <div className="mx-auto max-w-6xl space-y-6">
    <header><p className="text-xs font-semibold uppercase tracking-[.16em] text-[var(--ds-primary)]">Account</p><h1 className="mt-1 text-2xl font-semibold text-[var(--ds-text)]">Settings</h1><p className="mt-1 text-sm text-[var(--ds-text-muted)]">Manage your account, security, appearance, and notifications.</p></header>
    <div className="grid gap-6 lg:grid-cols-[190px_minmax(0,1fr)]">
      <nav aria-label="Settings" className="flex gap-1 overflow-x-auto border-b border-[var(--ds-border-subtle)] pb-2 lg:flex-col lg:border-b-0 lg:pb-0">
        {items.map(({to,label,icon:Icon,end})=><NavLink key={to} to={to} end={end} className={({isActive})=>cn("flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm transition",isActive?"bg-[var(--ds-primary-soft)] text-[var(--ds-primary)]":"text-[var(--ds-text-muted)] hover:bg-white/[.04] hover:text-[var(--ds-text)]")}><Icon size={16}/>{label}</NavLink>)}
      </nav>
      <main className="min-w-0 space-y-5">{children}</main>
    </div>
  </div>;
}

export function SettingsHeader({ title, description }: { title: string; description: string }) {
  return <div><h2 className="text-xl font-semibold text-[var(--ds-text)]">{title}</h2><p className="mt-1 text-sm text-[var(--ds-text-muted)]">{description}</p></div>;
}
