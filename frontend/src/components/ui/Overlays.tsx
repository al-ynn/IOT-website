import { useEffect, useId, useRef, type ReactNode } from "react";
import { createPortal } from "react-dom";
import { X } from "lucide-react";
import { cn } from "../../utils/cn";

interface OverlayProps {
  open: boolean;
  onClose(): void;
  title: string;
  description?: string;
  children: ReactNode;
  footer?: ReactNode;
}

function useOverlay(open: boolean, onClose: () => void) {
  const panel = useRef<HTMLElement | null>(null);
  const closeRef = useRef(onClose);
  useEffect(() => {
    closeRef.current = onClose;
  }, [onClose]);
  useEffect(() => {
    if (!open) return;
    const previousFocus =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const focusable = () =>
      Array.from(
        panel.current?.querySelectorAll<HTMLElement>(
          'button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])',
        ) ?? [],
      );
    const handler = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        event.preventDefault();
        closeRef.current();
        return;
      }
      if (event.key !== "Tab") return;
      const items = focusable();
      if (!items.length) {
        event.preventDefault();
        panel.current?.focus();
        return;
      }
      const first = items[0],
        last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    document.addEventListener("keydown", handler);
    requestAnimationFrame(() => focusable()[0]?.focus() ?? panel.current?.focus());
    return () => {
      document.removeEventListener("keydown", handler);
      document.body.style.overflow = previousOverflow;
      previousFocus?.focus();
    };
  }, [open]);
  return panel;
}

function Header({
  title,
  description,
  onClose,
  titleId,
  descriptionId,
}: {
  title: string;
  description?: string;
  onClose(): void;
  titleId: string;
  descriptionId: string;
}) {
  return (
    <div className="flex items-start justify-between gap-4 border-b border-[var(--ds-border-subtle)] px-5 py-3.5">
      <div>
        <h2 id={titleId} className="text-[14px] font-semibold text-[var(--ds-text)]">
          {title}
        </h2>
        {description && (
          <p id={descriptionId} className="mt-0.5 text-xs text-[var(--ds-text-muted)]">
            {description}
          </p>
        )}
      </div>
      <button
        type="button"
        aria-label={`Close ${title}`}
        onClick={onClose}
        className="grid h-8 w-8 place-items-center rounded-[8px] text-[var(--ds-text-subtle)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-text)]"
      >
        <X size={16} aria-hidden="true" />
      </button>
    </div>
  );
}

export function Modal({ open, onClose, title, description, children, footer }: OverlayProps) {
  const panel = useOverlay(open, onClose);
  const titleId = useId();
  const descriptionId = useId();
  if (!open) return null;
  return createPortal(
    <div className="fixed inset-0 z-[100] grid place-items-center p-4">
      <button
        type="button"
        aria-label={`Close ${title}`}
        className="absolute inset-0 bg-[var(--ds-overlay)] backdrop-blur-[6px]"
        onClick={onClose}
      />
      <section
        ref={panel}
        tabIndex={-1}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        className="relative w-full max-w-lg overflow-hidden rounded-[14px] border border-[var(--ds-border)] bg-[var(--ds-surface-elevated)] shadow-[var(--ds-shadow-lg)]"
      >
        <div aria-hidden className="tech-strip absolute inset-x-0 top-0 h-px opacity-70" />
        <Header
          title={title}
          description={description}
          onClose={onClose}
          titleId={titleId}
          descriptionId={descriptionId}
        />
        <div className="max-h-[70vh] overflow-y-auto p-5">{children}</div>
        {footer && (
          <div className="flex justify-end gap-2 border-t border-[var(--ds-border-subtle)] bg-[var(--ds-card-alt)] px-5 py-3">
            {footer}
          </div>
        )}
      </section>
    </div>,
    document.body,
  );
}

export function Drawer({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  side = "right",
}: OverlayProps & { side?: "left" | "right" }) {
  const panel = useOverlay(open, onClose);
  const titleId = useId();
  const descriptionId = useId();
  if (!open) return null;
  return createPortal(
    <div className="fixed inset-0 z-[100]">
      <button
        type="button"
        aria-label={`Close ${title}`}
        className="absolute inset-0 bg-[var(--ds-overlay)] backdrop-blur-[4px]"
        onClick={onClose}
      />
      <section
        ref={panel}
        tabIndex={-1}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        className={cn(
          "absolute inset-y-0 flex w-full max-w-md flex-col border-[var(--ds-border)] bg-[var(--ds-surface-elevated)] shadow-[var(--ds-shadow-lg)]",
          side === "right" ? "right-0 border-l" : "left-0 border-r",
        )}
      >
        <Header
          title={title}
          description={description}
          onClose={onClose}
          titleId={titleId}
          descriptionId={descriptionId}
        />
        <div className="flex-1 overflow-y-auto p-5">{children}</div>
        {footer && (
          <div className="flex justify-end gap-2 border-t border-[var(--ds-border-subtle)] bg-[var(--ds-card-alt)] p-4">
            {footer}
          </div>
        )}
      </section>
    </div>,
    document.body,
  );
}
