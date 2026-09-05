import {
  forwardRef,
  useId,
  type InputHTMLAttributes,
  type SelectHTMLAttributes,
  type TextareaHTMLAttributes,
  type ReactNode,
} from "react";
import { cn } from "../../utils/cn";

interface FieldProps {
  label?: string;
  helperText?: string;
  error?: string;
  leadingIcon?: ReactNode;
  trailingSlot?: ReactNode;
  className?: string;
}

/*
  Form controls — precise, dense, subtle depth (subtle inset shadow).
  Focus is a soft blue ring, error uses semantic danger.
*/
const control =
  "h-9 w-full rounded-[8px] border border-[var(--ds-border)] " +
  "bg-[var(--ds-surface)] px-3 text-sm text-[var(--ds-text)] " +
  "placeholder:text-[var(--ds-text-subtle)] " +
  "shadow-[inset_0_1px_1px_var(--ds-inset-shadow)] " +
  "hover:border-[var(--ds-border-strong)] " +
  "focus:border-[var(--ds-primary)] focus:outline-none " +
  "focus:ring-2 focus:ring-[var(--ds-primary-outline)] focus:ring-offset-0 " +
  "disabled:cursor-not-allowed disabled:opacity-55";

function Field({
  id,
  label,
  helperText,
  error,
  children,
  className,
}: { id: string; children: ReactNode } & FieldProps) {
  return (
    <div className={cn("space-y-1.5", className)}>
      {label && (
        <label
          htmlFor={id}
          className="block text-[11.5px] font-medium text-[var(--ds-text-muted)]"
        >
          {label}
        </label>
      )}
      {children}
      {error ? (
        <p id={`${id}-error`} role="alert" className="text-xs text-[var(--ds-danger)]">
          {error}
        </p>
      ) : (
        helperText && (
          <p id={`${id}-help`} className="text-xs text-[var(--ds-text-subtle)]">
            {helperText}
          </p>
        )
      )}
    </div>
  );
}

export const Input = forwardRef<
  HTMLInputElement,
  InputHTMLAttributes<HTMLInputElement> & FieldProps
>(({ label, helperText, error, leadingIcon, trailingSlot, className, id, ...props }, ref) => {
  const generated = useId();
  const fieldId = id ?? generated;
  return (
    <Field id={fieldId} label={label} helperText={helperText} error={error} className={className}>
      <div className="relative">
        {leadingIcon && (
          <span className="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-[var(--ds-text-subtle)] [&>svg]:h-4 [&>svg]:w-4">
            {leadingIcon}
          </span>
        )}
        <input
          ref={ref}
          id={fieldId}
          aria-invalid={!!error}
          aria-describedby={error ? `${fieldId}-error` : helperText ? `${fieldId}-help` : undefined}
          className={cn(
            control,
            leadingIcon && "pl-9",
            trailingSlot && "pr-9",
            error && "border-[var(--ds-danger)] focus:ring-[var(--ds-danger-surface)]",
          )}
          {...props}
        />
        {trailingSlot && (
          <span className="absolute inset-y-0 right-2.5 flex items-center text-[var(--ds-text-subtle)]">
            {trailingSlot}
          </span>
        )}
      </div>
    </Field>
  );
});
Input.displayName = "Input";

export const Select = forwardRef<
  HTMLSelectElement,
  SelectHTMLAttributes<HTMLSelectElement> & FieldProps
>(({ label, helperText, error, className, id, children, ...props }, ref) => {
  const generated = useId();
  const fieldId = id ?? generated;
  return (
    <Field id={fieldId} label={label} helperText={helperText} error={error} className={className}>
      <select
        ref={ref}
        id={fieldId}
        aria-invalid={!!error}
        className={cn(
          control,
          "appearance-none bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%239CA3AF%22 stroke-width=%222.5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><polyline points=%226 9 12 15 18 9%22/></svg>')] bg-[length:12px] bg-[right_.75rem_center] bg-no-repeat pr-9",
          error && "border-[var(--ds-danger)]",
        )}
        {...props}
      >
        {children}
      </select>
    </Field>
  );
});
Select.displayName = "Select";

export const Textarea = forwardRef<
  HTMLTextAreaElement,
  TextareaHTMLAttributes<HTMLTextAreaElement> & FieldProps
>(({ label, helperText, error, className, id, ...props }, ref) => {
  const generated = useId();
  const fieldId = id ?? generated;
  return (
    <Field id={fieldId} label={label} helperText={helperText} error={error} className={className}>
      <textarea
        ref={ref}
        id={fieldId}
        aria-invalid={!!error}
        className={cn(
          control,
          "h-auto min-h-20 resize-y py-2 leading-5",
          error && "border-[var(--ds-danger)]",
        )}
        {...props}
      />
    </Field>
  );
});
Textarea.displayName = "Textarea";

/* Toggle switch — subtle tactile 3D (Pic 6) */
export function Switch({
  checked,
  onChange,
  disabled,
  label,
  className,
  id,
}: {
  checked: boolean;
  onChange(next: boolean): void;
  disabled?: boolean;
  label?: string;
  className?: string;
  id?: string;
}) {
  const generated = useId();
  const controlId = id ?? generated;
  return (
    <label
      htmlFor={controlId}
      className={cn(
        "inline-flex items-center gap-2.5 select-none",
        disabled && "opacity-55 pointer-events-none",
        className,
      )}
    >
      <button
        id={controlId}
        role="switch"
        aria-checked={checked}
        aria-label={label}
        disabled={disabled}
        type="button"
        onClick={() => onChange(!checked)}
        className={cn(
          "relative inline-flex h-5 w-9 shrink-0 items-center rounded-full border transition-colors",
          "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)] focus-visible:ring-offset-1",
          checked
            ? "border-transparent bg-[var(--ds-primary)] shadow-[inset_0_1px_1px_rgb(0_0_0/.15),0_0_10px_-2px_rgb(59_130_246/.5)]"
            : "border-[var(--ds-border)] bg-[var(--ds-surface)] shadow-[inset_0_1px_2px_var(--ds-inset-shadow)]",
        )}
      >
        <span
          aria-hidden
          className={cn(
            "inline-block h-4 w-4 rounded-full bg-white shadow-[0_1px_2px_rgb(0_0_0/.3)] transition-transform",
            checked ? "translate-x-[18px]" : "translate-x-[2px]",
          )}
        />
      </button>
      {label && <span className="text-xs text-[var(--ds-text-muted)]">{label}</span>}
    </label>
  );
}

/* Slider — subtle glow on the filled track */
export function Slider({
  value,
  min = 0,
  max = 100,
  step = 1,
  onChange,
  className,
  id,
  label,
  disabled,
}: {
  value: number;
  min?: number;
  max?: number;
  step?: number;
  onChange(next: number): void;
  className?: string;
  id?: string;
  label?: string;
  disabled?: boolean;
}) {
  const generated = useId();
  const controlId = id ?? generated;
  const pct = ((value - min) / (max - min || 1)) * 100;
  return (
    <div className={cn("space-y-1", className)}>
      {label && (
        <label htmlFor={controlId} className="block text-[11.5px] font-medium text-[var(--ds-text-muted)]">
          {label}
        </label>
      )}
      <input
        id={controlId}
        type="range"
        value={value}
        min={min}
        max={max}
        step={step}
        disabled={disabled}
        onChange={(event) => onChange(Number(event.target.value))}
        className="w-full appearance-none bg-transparent focus:outline-none disabled:opacity-55 [&::-webkit-slider-runnable-track]:h-1.5 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white [&::-webkit-slider-thumb]:shadow-[0_1px_3px_rgb(0_0_0/.35),0_0_0_2px_var(--ds-primary)] [&::-webkit-slider-thumb]:-mt-[5px] [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-[var(--ds-primary)] [&::-moz-range-thumb]:bg-white"
        style={{
          background: `linear-gradient(to right, var(--ds-primary) 0%, var(--ds-primary) ${pct}%, var(--ds-border) ${pct}%, var(--ds-border) 100%)`,
          borderRadius: 999,
          height: 6,
        }}
      />
    </div>
  );
}
