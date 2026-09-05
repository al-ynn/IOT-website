import type { ResourcePublicationVersion } from "../../types/publication-version";

export function PublicationVersionList({ versions }: { versions: ResourcePublicationVersion[] }) {
  return (
    <section aria-labelledby="published-versions-title">
      <h2 id="published-versions-title">Published Versions</h2>
      <ol className="mt-3 space-y-3">
        {versions.map((version) => (
          <li key={version.id} className="rounded-lg border border-[var(--ds-border-subtle)] p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <strong>Version {version.number}</strong>
              <span aria-label={version.current ? "Current published version" : "Previous published version"}>
                {version.current ? "Current" : "Previous Version"}
              </span>
            </div>
            <p>Resource Revision {version.revision.number}</p>
            <p className="text-xs text-[var(--ds-text-muted)]">
              Published by {version.publishedBy?.name ?? "Unavailable account"} on{" "}
              <time dateTime={version.publishedAt}>{new Date(version.publishedAt).toLocaleString()}</time>
            </p>
            <p className="mt-2 text-xs font-medium">Read-only published configuration</p>
          </li>
        ))}
      </ol>
    </section>
  );
}
