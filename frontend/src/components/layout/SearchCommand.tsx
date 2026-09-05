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
    <button type="button" onClick={() => setOpen(true)} aria-label="Open resource search" className="hidden h-9 items-center gap-2 rounded-[8px] border border-[var(--ds-border-subtle)] bg-[var(--ds-surface)] px-3 text-xs text-[var(--ds-text-subtle)] hover:border-[var(--ds-border)] sm:flex">
      <Search size={14}/>Search<span className="ml-5 rounded border border-[var(--ds-border)] px-1.5 py-0.5 text-[10px]">Ctrl K</span>
    </button>
    {open && <div className="fixed inset-0 z-[70] flex items-start justify-center bg-[var(--ds-overlay)] px-4 pt-[12vh]" role="dialog" aria-modal="true" aria-labelledby="resource-search-title">
      <button type="button" aria-label="Close search" className="absolute inset-0" onClick={() => setOpen(false)}/>
      <form onSubmit={submit} className="relative w-full max-w-xl rounded-[10px] border border-[var(--ds-border)] bg-[var(--ds-surface-elevated)] p-3 shadow-2xl">
        <h2 id="resource-search-title" className="sr-only">Search accessible resources</h2>
        <button type="button" onClick={() => setOpen(false)} aria-label="Close search" className="absolute right-4 top-4 z-10 text-[var(--ds-text-subtle)]"><X size={16}/></button>
        <Input ref={input} aria-label="Search accessible resources" placeholder="Search accessible resources" leadingIcon={<Search size={15}/>} value={query} onChange={event => setQuery(event.target.value)} minLength={2} maxLength={100} className="pr-8"/>
        <div className="mt-3 flex items-center justify-between gap-3">
          <p className="text-xs text-[var(--ds-text-subtle)]">Enter at least 2 characters.</p>
          <button type="submit" disabled={query.trim().length < 2} className="rounded-md bg-[var(--ds-primary-hover)] px-3 py-2 text-xs font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">Search resources</button>
        </div>
      </form>
    </div>}
  </>;
}
