import type { ReactNode } from "react";

/*
  Auth layout — dark/light aware centered panel with subtle technical grid backdrop.
  Preserves prop contract (children).
*/
export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="app-backdrop min-h-screen">
      <div className="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <div className="w-full max-w-md">
          <div className="relative overflow-hidden rounded-[14px] border border-[var(--ds-border)] bg-[var(--ds-surface-elevated)] p-6 shadow-[var(--ds-shadow-lg)] sm:p-8">
            <div aria-hidden className="tech-strip absolute inset-x-0 top-0 h-px opacity-80" />
            <div
              aria-hidden
              className="pointer-events-none absolute -right-24 -top-24 h-56 w-56 rounded-full opacity-30 blur-3xl"
              style={{ background: "var(--ds-primary)" }}
            />
            {children}
          </div>
        </div>
      </div>
    </div>
  );
}
