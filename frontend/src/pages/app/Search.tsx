import { useEffect, useState, type FormEvent } from "react";
import { Search as SearchIcon } from "lucide-react";
import { Link, useSearchParams } from "react-router-dom";
import { Button, Input } from "../../components/ui";
import { searchResources } from "../../services/search.service";
import type { SearchResponse } from "../../types/search";

interface SearchState {
  key: string;
  response: SearchResponse | null;
  error: string;
}

export default function Search() {
  const [params, setParams] = useSearchParams();
  const query = params.get("q")?.trim() ?? "";
  const page = Math.max(1, Number(params.get("page") ?? 1) || 1);
  const requestKey = query.length >= 2 ? `${query}\u0000${page}` : "";
  const [draft, setDraft] = useState(query);
  const [state, setState] = useState<SearchState>({ key: "", response: null, error: "" });

  useEffect(() => {
    if (!requestKey) return;
    const controller = new AbortController();
    void searchResources(query, page, controller.signal)
      .then(response => {
        if (!controller.signal.aborted) setState({ key: requestKey, response, error: "" });
      })
      .catch(error => {
        if (!controller.signal.aborted && error?.code !== "ERR_CANCELED") {
          setState({ key: requestKey, response: null, error: "Search could not be completed. Try again." });
        }
      });
    return () => controller.abort();
  }, [page, query, requestKey]);

  const current = state.key === requestKey ? state : null;
  const response = current?.response ?? null;
  const items = response?.data ?? [];
  const loading = requestKey.length > 0 && current === null;
  const error = current?.error ?? "";

  function submit(event: FormEvent) {
    event.preventDefault();
    const next = draft.trim();
    if (next.length >= 2) setParams({ q: next });
  }

  function goToPage(next: number) {
    setParams({ q: query, page: String(next) });
  }

  return <section className="mx-auto w-full max-w-5xl space-y-5">
    <div><h1 className="text-xl font-semibold text-[var(--ds-text)]">Search</h1><p className="mt-1 text-sm text-[var(--ds-text-muted)]">Find resources you can currently access.</p></div>
    <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row" role="search">
      <Input aria-label="Search accessible resources" value={draft} onChange={event => setDraft(event.target.value)} placeholder="Search by resource name or ID" leadingIcon={<SearchIcon size={16}/>} minLength={2} maxLength={100}/>
      <Button type="submit" disabled={draft.trim().length < 2 || loading}>{loading ? "Searching…" : "Search"}</Button>
    </form>
    {!requestKey && <p className="rounded-lg border border-[var(--ds-border)] p-6 text-center text-sm text-[var(--ds-text-muted)]">Search for devices, templates, dashboards, and other accessible resources.</p>}
    <div aria-live="polite">{loading && <p className="text-sm text-[var(--ds-text-muted)]">Searching accessible resources…</p>}{error && <p role="alert" className="text-sm text-[var(--ds-danger)]">{error}</p>}</div>
    {!loading && !error && requestKey && response && items.length === 0 && <p className="rounded-lg border border-[var(--ds-border)] p-6 text-center text-sm text-[var(--ds-text-muted)]">No accessible resources found.</p>}
    {items.length > 0 && <ul aria-label="Search results" className="divide-y divide-[var(--ds-border)] rounded-lg border border-[var(--ds-border)] bg-[var(--ds-surface)]">
      {items.map(item => <li key={item.resourceType+item.resourceId} className="flex flex-wrap items-center justify-between gap-3 p-4">
        <div className="min-w-0"><p className="break-words font-medium text-[var(--ds-text)]">{item.label}</p><p className="mt-1 text-xs capitalize text-[var(--ds-text-muted)]">{item.resourceType.replaceAll("_", " ")} · {item.lifecycle} · {item.accessMode.replaceAll("_", " ")}</p>{(item.matchKind === "metadata" || item.matchKind === "child_metadata") && <p className="mt-1 break-words text-xs text-[var(--ds-text-subtle)]">{item.matchedFieldLabel}: {item.matchedContext}</p>}</div>
        <Link className="min-h-10 rounded-md border border-[var(--ds-border)] px-3 py-2 text-xs font-medium text-[var(--ds-text)] hover:bg-white/[.05] focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)]" to={item.destination}>Open <span className="sr-only">{item.label}</span></Link>
      </li>)}
    </ul>}
    {response && response.last_page > 1 && <nav aria-label="Search results pages" className="flex items-center justify-between gap-3">
      <Button type="button" variant="secondary" disabled={response.current_page <= 1 || loading} onClick={() => goToPage(response.current_page - 1)}>Previous</Button>
      <p className="text-xs text-[var(--ds-text-muted)]">Page {response.current_page} of {response.last_page}</p>
      <Button type="button" variant="secondary" disabled={response.current_page >= response.last_page || loading} onClick={() => goToPage(response.current_page + 1)}>Next</Button>
    </nav>}
  </section>;
}
