import { ChevronLeft, ChevronRight } from "lucide-react";
import { Link, useLocation } from "react-router-dom";
import { primaryNavigationSectionLabels, routeMatches, type PrimaryNavigationItem } from "../../navigation/app-navigation";

/*
  Primary navigation — premium technical rail (refs 8/9/10):
  dark glass panel, gradient logo chip with glow, section labels with
  hairline rules, active item gets luminous blue pill + left beam.
*/
export default function PrimarySidebar({
  items,
  collapsed = false,
  onToggle,
  onNavigate,
  homePath = "/app/dashboard",
}: {
  items: PrimaryNavigationItem[];
  collapsed?: boolean;
  onToggle?: () => void;
  onNavigate?: () => void;
  homePath?: string;
}) {
  const { pathname } = useLocation();
  const sections = Array.from(new Set(items.map((entry) => entry.section)));

  return (
    <aside
      aria-label="Primary navigation"
      className="relative flex h-full flex-col border-r border-[var(--ds-border-subtle)] text-[var(--ds-text)]"
      style={{
        background:
          "linear-gradient(180deg, color-mix(in oklab, var(--ds-surface) 92%, transparent), color-mix(in oklab, var(--ds-surface) 96%, transparent))",
      }}
    >
      {/* ambient glow at top of rail */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 h-40"
        style={{ background: "radial-gradient(200px 120px at 50% -30%, var(--ds-ambient-1), transparent 70%)" }}
      />

      {/* Brand */}
      <div className={`relative flex h-14 shrink-0 items-center border-b border-[var(--ds-border-subtle)] ${collapsed ? "justify-center gap-0.5 px-1" : "gap-2 px-3"}`}>
        <Link
          to={homePath}
          onClick={onNavigate}
          aria-label="IoT Platform home"
          title={collapsed ? "IoT Platform" : undefined}
          className="group flex min-w-0 items-center gap-2.5 overflow-hidden"
        >
          <span
            className={`${collapsed ? "h-7 w-7 text-[11px]" : "h-8 w-8 text-xs"} grid shrink-0 place-items-center rounded-[9px] font-bold text-white`}
            style={{
              background: "linear-gradient(135deg, var(--ds-primary), var(--ds-primary-strong))",
              boxShadow: "0 0 0 1px rgb(255 255 255 / .12) inset, 0 4px 14px -4px var(--ds-primary-glow), 0 0 18px -6px var(--ds-chart-3)",
            }}
          >
            I
          </span>
          {!collapsed && (
            <span className="min-w-0">
              <span className="block truncate whitespace-nowrap text-[11px] font-bold tracking-[0.16em]">IOT PLATFORM</span>
              <span className="block text-[9px] font-medium uppercase tracking-[0.2em] text-[var(--ds-text-subtle)]">Command Center</span>
            </span>
          )}
        </Link>
        {onToggle && (
          <button
            type="button"
            aria-label={collapsed ? "Expand primary navigation" : "Collapse primary navigation"}
            aria-expanded={!collapsed}
            onClick={onToggle}
            title={collapsed ? "Expand navigation" : "Collapse navigation"}
            className={`${collapsed ? "h-7 w-7" : "h-8 w-8"} grid shrink-0 place-items-center rounded-[8px] border border-[var(--ds-border-subtle)] text-[var(--ds-text-muted)] hover:border-[var(--ds-primary-outline)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)] focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)]`}
          >
            {collapsed ? <ChevronRight size={14} /> : <ChevronLeft size={14} />}
          </button>
        )}
      </div>

      {/* Nav */}
      <nav aria-label="Platform areas" className="relative flex-1 overflow-y-auto px-2 py-3">
        {sections.map((section, index) => (
          <section key={section} aria-labelledby={collapsed ? undefined : `primary-${section}`} className={index ? "mt-5" : ""}>
            {!collapsed && (
              <h2
                id={`primary-${section}`}
                className="mb-1.5 flex items-center gap-2 px-2 text-[9.5px] font-semibold uppercase tracking-[0.18em] text-[var(--ds-text-subtle)]"
              >
                {primaryNavigationSectionLabels[section]}
                <span aria-hidden className="h-px flex-1 bg-gradient-to-r from-[color-mix(in_oklab,var(--ds-chart-3)_55%,transparent)] to-transparent" />
              </h2>
            )}
            <div className="space-y-0.5">
              {items
                .filter((entry) => entry.section === section)
                .map((entry) => {
                  const Icon = entry.icon;
                  const active = routeMatches(pathname, entry.matches);
                  return (
                    <Link
                      key={entry.id}
                      to={entry.path}
                      onClick={onNavigate}
                      aria-current={active ? "page" : undefined}
                      aria-label={collapsed ? entry.label : undefined}
                      title={collapsed ? entry.label : undefined}
                      className={`group relative flex min-h-9 items-center gap-2.5 rounded-[9px] px-2.5 text-xs font-medium transition focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)] ${
                        active
                          ? "text-[var(--ds-chart-3)]"
                          : "text-[var(--ds-text-muted)] hover:text-[var(--ds-text)]"
                      }`}
                      style={
                        active
                          ? {
                              background:
                                "linear-gradient(90deg, color-mix(in oklab, var(--ds-chart-3) 22%, transparent), color-mix(in oklab, var(--ds-chart-3) 5%, transparent))",
                              boxShadow:
                                "inset 0 0 0 1px color-mix(in oklab, var(--ds-chart-3) 45%, transparent), 0 0 20px -6px var(--ds-chart-3)",
                            }
                          : undefined
                      }
                    >
                      {/* active left beam */}
                      {active && (
                        <span
                          aria-hidden
                          className="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full"
                          style={{
                            background: "linear-gradient(180deg, var(--ds-chart-3), var(--ds-chart-4))",
                            boxShadow: "0 0 12px 2px var(--ds-chart-3)",
                          }}
                        />
                      )}
                      <span
                        className={`grid h-6 w-6 shrink-0 place-items-center rounded-[7px] transition ${
                          active
                            ? "text-[var(--ds-primary-soft)]"
                            : "text-[var(--ds-text-subtle)] group-hover:bg-[var(--ds-primary-surface)] group-hover:text-[var(--ds-primary)]"
                        }`}
                      >
                        <Icon size={15} aria-hidden="true" />
                      </span>
                      {!collapsed && <span className="truncate">{entry.label}</span>}
                      {!collapsed && active && (
                        <span aria-hidden className="ml-auto h-1.5 w-1.5 rounded-full bg-[var(--ds-chart-3)] shadow-[0_0_10px_2px_var(--ds-chart-3)]" />
                      )}
                    </Link>
                  );
                })}
            </div>
          </section>
        ))}
      </nav>

      {/* bottom status chip */}
      {!collapsed && (
        <div className="relative border-t border-[var(--ds-border-subtle)] p-3">
          <div className="flex items-center gap-2 rounded-[9px] border border-[color-mix(in_oklab,var(--ds-chart-3)_35%,transparent)] bg-[var(--ds-card-alt)] px-2.5 py-2" style={{boxShadow:"0 0 14px -6px var(--ds-chart-3)"}}>
            <span aria-hidden className="pulse-dot h-2 w-2 rounded-full bg-[var(--ds-chart-2)]" />
            <span className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[var(--ds-chart-3)]">Systems nominal</span>
          </div>
        </div>
      )}
    </aside>
  );
}
