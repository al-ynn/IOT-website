import type {ReactNode} from "react";
import {Link} from "react-router-dom";

/*
  Auth layout v3 — cinematic IoT command-center entrance.
  Radar pulse rings, sweeping scan line, animated circuit traces,
  floating live-telemetry chips, equalizer brand mark, glass panel,
  scrolling ops ticker. All decorative layers are aria-hidden and
  pointer-events-free; the form contract (children) is untouched.
*/
const chips=[
  {label:"TEMP",value:"23.5°C",color:"var(--ds-chart-1)",style:{top:"15%",left:"7%"},delay:"0s"},
  {label:"SIGNAL",value:"-62 dBm",color:"var(--ds-chart-2)",style:{top:"28%",right:"8%"},delay:"1.2s"},
  {label:"UPTIME",value:"99.98%",color:"var(--ds-chart-3)",style:{bottom:"30%",left:"9%"},delay:"2.1s"},
  {label:"NODES",value:"128",color:"var(--ds-chart-4)",style:{bottom:"20%",right:"11%"},delay:".6s"},
];

const ticker=["MQTT OVER TLS 1.3","EDGE NODES: 128 ONLINE","LATENCY 12MS","FIRMWARE 4.2.1 STABLE","TELEMETRY 4.8K PTS/S","ZERO-TRUST SESSION","GEO-FENCE ACTIVE","OTA CHANNEL READY"];

export default function AuthLayout({children}:{children:ReactNode}){
  return <div className="app-backdrop relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-12 sm:px-6">
    {/* radar pulse rings */}
    <span aria-hidden className="auth-radar"/>
    {/* sweeping scan line */}
    <span aria-hidden className="auth-scan"/>
    {/* animated circuit traces */}
    <svg aria-hidden className="pointer-events-none absolute inset-0 h-full w-full opacity-40" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice">
      <path className="circuit-path" d="M0 120 H300 L360 180 H760" fill="none" stroke="var(--ds-primary-outline)" strokeWidth="1"/>
      <path className="circuit-path" d="M1200 640 H880 L820 580 H440" fill="none" stroke="color-mix(in oklab, var(--ds-chart-3) 40%, transparent)" strokeWidth="1"/>
      <path className="circuit-path" d="M120 800 V560 L180 500 V360" fill="none" stroke="var(--ds-primary-outline)" strokeWidth="1"/>
      <circle cx="300" cy="120" r="3" fill="var(--ds-primary)"/>
      <circle cx="880" cy="640" r="3" fill="var(--ds-chart-3)"/>
      <circle cx="180" cy="500" r="3" fill="var(--ds-primary-soft)"/>
    </svg>
    {/* floating telemetry chips */}
    {chips.map(c=><div key={c.label} aria-hidden className="auth-chip" style={{...c.style,animationDelay:c.delay}}>
      <span className="h-1.5 w-1.5 rounded-full" style={{background:c.color,boxShadow:`0 0 8px 1px ${c.color}`}}/>
      <span className="text-[9px] font-bold uppercase tracking-[0.18em] text-[var(--ds-text-subtle)]">{c.label}</span>
      <span className="text-[11px] font-bold tabular-nums" data-numeric style={{color:c.color}}>{c.value}</span>
    </div>)}

    {/* brand */}
    <Link to="/" className="relative mb-8 flex flex-col items-center gap-3" aria-label="IoT Platform home">
      <span className="relative grid h-14 w-14 place-items-center rounded-[16px] text-xl font-bold text-white" style={{background:"linear-gradient(135deg, var(--ds-primary), var(--ds-primary-strong))",boxShadow:"0 0 0 1px rgb(255 255 255 / .12) inset, 0 8px 28px -6px var(--ds-primary-glow), 0 0 48px -8px var(--ds-primary-glow)"}}>
        I
        <span aria-hidden className="pulse-dot absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-[var(--ds-bg)] bg-[var(--ds-chart-2)]"/>
      </span>
      <span className="text-center">
        <span className="block text-sm font-bold tracking-[0.24em] text-[var(--ds-text)]">IOT PLATFORM</span>
        <span className="mt-1.5 flex items-center justify-center gap-2 text-[10px] font-semibold uppercase tracking-[0.28em] text-[var(--ds-chart-2)]">
          <span aria-hidden className="flex h-3 items-end gap-[2.5px]">
            <span className="eq-bar block h-full w-[2.5px] rounded-full bg-[var(--ds-chart-2)]"/>
            <span className="eq-bar block h-full w-[2.5px] rounded-full bg-[var(--ds-chart-2)]"/>
            <span className="eq-bar block h-full w-[2.5px] rounded-full bg-[var(--ds-chart-3)]"/>
            <span className="eq-bar block h-full w-[2.5px] rounded-full bg-[var(--ds-chart-2)]"/>
          </span>
          System online
        </span>
      </span>
    </Link>

    {/* glass login panel */}
    <div className="relative w-full max-w-md">
      <div aria-hidden className="pointer-events-none absolute -inset-12 rounded-[36px]" style={{background:"radial-gradient(closest-side, var(--ds-ambient-1), transparent 72%)"}}/>
      <div className="tech-corners luminous-top surface-glass relative overflow-hidden rounded-[16px] border border-[var(--ds-border)] p-6 sm:p-8" style={{boxShadow:"var(--ds-shadow-lg)"}}>
        {children}
      </div>
    </div>

    {/* ops ticker */}
    <div className="auth-ticker mt-8 w-full max-w-lg">
      <div className="auth-ticker-inner text-[9.5px] font-semibold uppercase tracking-[0.22em] text-[var(--ds-text-subtle)]">
        {[...ticker,...ticker].map((t,i)=><span key={i} className="flex items-center gap-2"><span aria-hidden className="h-1 w-1 rounded-full bg-[var(--ds-chart-3)]"/>{t}</span>)}
      </div>
    </div>
  </div>;
}
