import { useEffect, useRef, useState, type FormEvent } from "react";
import { Search, X } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Input } from "../ui";

export default function SearchCommand() {
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const input = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const key = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        setOpen(true);
      }
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("keydown", key);
    return () => document.removeEventListener("keydown", key);
  }, []);
  useEffect(() => { if (open) setTimeout(() => input.current?.focus(), 0); }, [open]);

  function submit(event: FormEvent) {
    event.preventDefault();
    const value = query.trim();
    if (value.length < 2) return;
    setOpen(false);
    setQuery("");
    navigate("/app/search?q=" + encodeURIComponent(value));
  }

  return <>
    <button type="button" onClick={() => setOpen(true)} aria-label="Open resource search" className="hidden h-9 items-center gap-2 rounded-[9px] border border-[var(--ds-border-subtle)] bg-[var(--ds-card)] px-3 text-xs text-[var(--ds-text-subtle)] hover:border-[var(--ds-primary-outline)] hover:text-[var(--ds-text-muted)] sm:flex">
      <Search size={14}/>Search<span className="ml-5 rounded-[5px] border border-[var(--ds-border)] bg-[var(--ds-card-alt)] px-1.5 py-0.5 text-[10px] font-medium">Ctrl K</span>
    </button>
    {open && <div className="fixed inset-0 z-[70] flex items-start justify-center bg-[var(--ds-overlay)] px-4 pt-[12vh] backdrop-blur-[6px]" role="dialog" aria-modal="true" aria-labelledby="resource-search-title">
      <button type="button" aria-label="Close search" className="absolute inset-0" onClick={() => setOpen(false)}/>
      <form onSubmit={submit} className="luminous-top relative w-full max-w-xl rounded-[12px] border border-[var(--ds-border)] bg-[var(--ds-overlay-panel)] p-3" style={{boxShadow:"var(--ds-shadow-lg)"}}>
        <h2 id="resource-search-title" className="sr-only">Search accessible resources</h2>
        <button type="button" onClick={() => setOpen(false)} aria-label="Close search" className="absolute right-4 top-4 z-10 grid h-7 w-7 place-items-center rounded-[7px] text-[var(--ds-text-subtle)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-text)]"><X size={15}/></button>
        <Input ref={input} aria-label="Search accessible resources" placeholder="Search accessible resources" leadingIcon={<Search size={15}/>} value={query} onChange={event => setQuery(event.target.value)} minLength={2} maxLength={100} className="pr-8"/>
        <div className="mt-3 flex items-center justify-between gap-3">
          <p className="text-xs text-[var(--ds-text-subtle)]">Enter at least 2 characters.</p>
          <button type="submit" disabled={query.trim().length < 2} className="rounded-[8px] px-3.5 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" style={{background:"linear-gradient(135deg, var(--ds-primary), var(--ds-primary-strong))",boxShadow:"0 4px 14px -4px var(--ds-primary-glow)"}}>Search resources</button>
        </div>
      </form>
    </div>}
  </>;
}
