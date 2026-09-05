import type { Key, ReactNode } from "react";
import { cn } from "../../utils/cn";

export interface DataTableColumn<T> {
  key: string;
  header: ReactNode;
  render(row: T): ReactNode;
  align?: "left" | "center" | "right";
  width?: string;
}

export interface DataTableProps<T> {
  rows: T[];
  columns: DataTableColumn<T>[];
  getRowKey(row: T): Key;
  caption?: string;
  empty?: ReactNode;
  compact?: boolean;
  stickyHeader?: boolean;
  className?: string;
  onRowClick?(row: T): void;
}

/*
  DataTable — compact, sortable-friendly, hover-highlight.
  Sticky header option for tall tables. Row hover uses primary-surface tint.
*/
export function DataTable<T>({
  rows,
  columns,
  getRowKey,
  caption,
  empty = "No records available.",
  compact = true,
  stickyHeader = false,
  className,
  onRowClick,
}: DataTableProps<T>) {
  return (
    <div
      className={cn(
        "overflow-x-auto rounded-[10px] border border-[var(--ds-border-subtle)] bg-[var(--ds-card)]",
        className,
      )}
    >
      <table className="w-full border-collapse text-left text-sm">
        {caption && <caption className="sr-only">{caption}</caption>}
        <thead
          className={cn(
            "bg-[var(--ds-card-alt)] text-[10.5px] font-semibold uppercase tracking-[.08em] text-[var(--ds-text-subtle)]",
            stickyHeader && "sticky top-0 z-10 backdrop-blur",
          )}
        >
          <tr>
            {columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                style={{ width: column.width }}
                className={cn(
                  "whitespace-nowrap border-b border-[var(--ds-border-subtle)] px-3",
                  compact ? "h-9" : "h-11",
                  column.align === "center" && "text-center",
                  column.align === "right" && "text-right",
                )}
              >
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr
              key={getRowKey(row)}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
              className={cn(
                "border-b border-[var(--ds-border-subtle)] last:border-0 text-[var(--ds-text)]",
                "hover:bg-[var(--ds-primary-surface)] transition-colors",
                onRowClick && "cursor-pointer",
              )}
            >
              {columns.map((column) => (
                <td
                  key={column.key}
                  className={cn(
                    "px-3 text-[var(--ds-text-muted)] align-middle",
                    compact ? "h-10" : "h-12",
                    column.align === "center" && "text-center",
                    column.align === "right" && "text-right",
                  )}
                >
                  {column.render(row)}
                </td>
              ))}
            </tr>
          ))}
          {rows.length === 0 && (
            <tr>
              <td
                colSpan={columns.length}
                className="h-28 text-center text-sm text-[var(--ds-text-subtle)]"
              >
                {empty}
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
