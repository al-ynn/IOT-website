import type {ReactNode} from "react";
import {Link} from "react-router-dom";

/*
  Auth layout v2 — premium IoT command-center entrance (refs 8/9/10):
  technical grid + dot matrix backdrop, ambient blue glows, glass panel
  with luminous top edge + corner ticks, gradient brand mark.
  Functional contract preserved (children only).
*/
export default function AuthLayout({children}:{children:ReactNode}){
  return <div className="app-backdrop flex min-h-screen flex-col items-center justify-center px-4 py-12 sm:px-6">
    <Link to="/" className="mb-8 flex flex-col items-center gap-3" aria-label="IoT Platform home">
      <span aria-hidden className="grid h-12 w-12 place-items-center rounded-[14px] text-lg font-bold text-white" style={{background:"linear-gradient(135deg, var(--ds-primary), var(--ds-primary-strong))",boxShadow:"0 0 0 1px rgb(255 255 255 / .12) inset, 0 8px 28px -6px var(--ds-primary-glow), 0 0 44px -8px var(--ds-primary-glow)"}}>I</span>
      <span className="text-center">
        <span className="block text-sm font-bold tracking-[0.22em] text-[var(--ds-text)]">IOT PLATFORM</span>
        <span className="mt-0.5 block text-[10px] font-medium uppercase tracking-[0.28em] text-[var(--ds-text-subtle)]">Command Center</span>
      </span>
    </Link>
    <div className="relative w-full max-w-md">
      <div aria-hidden className="pointer-events-none absolute -inset-10 rounded-[32px]" style={{background:"radial-gradient(closest-side, var(--ds-ambient-1), transparent 72%)"}}/>
      <div className="tech-corners luminous-top surface-glass relative overflow-hidden rounded-[16px] border border-[var(--ds-border)] p-6 sm:p-8" style={{boxShadow:"var(--ds-shadow-lg)"}}>
        {children}
      </div>
      <p className="mt-5 text-center text-[10px] font-medium uppercase tracking-[0.2em] text-[var(--ds-text-subtle)]">Secure access · Encrypted session</p>
    </div>
  </div>;
}
