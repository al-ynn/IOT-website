import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from "react";
import { cn } from "../../utils/cn";

export type ButtonVariant = "primary" | "secondary" | "outline" | "ghost" | "danger";
export type ButtonSize = "compact" | "small" | "sm" | "default";
export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> { variant?: ButtonVariant; size?: ButtonSize; loading?: boolean; leadingIcon?: ReactNode; asChild?: boolean }
const variants = {
  primary: "border-transparent bg-[var(--ds-primary-hover)] text-white hover:brightness-110",
  secondary: "border-[var(--ds-border-subtle)] bg-[var(--ds-surface-elevated)] text-[var(--ds-text)] hover:bg-white/[.07]",
  outline: "border-[var(--ds-border)] bg-transparent text-[var(--ds-text)] hover:bg-white/[.04]",
  ghost: "border-transparent bg-transparent text-[var(--ds-text-muted)] hover:bg-white/[.05] hover:text-[var(--ds-text)]",
  danger: "border-transparent bg-[var(--ds-danger)] text-white hover:brightness-110",
};
const sizes = { compact: "h-7 gap-1.5 px-2.5 text-xs", small: "h-8 gap-2 px-3 text-xs", sm: "h-8 gap-2 px-3 text-xs", default: "h-9 gap-2 px-3.5 text-sm" };
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(({ className, variant = "primary", size = "default", loading = false, leadingIcon, disabled, children, asChild = false, ...props }, ref) => {
  const styles = cn("inline-flex items-center justify-center rounded-[var(--radius-md,10px)] border font-medium shadow-sm transition focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)] disabled:pointer-events-none disabled:opacity-100", variants[variant], sizes[size], className);
  if (asChild) return <span className={styles}>{children}</span>;
  return <button ref={ref} disabled={disabled || loading} aria-busy={loading || undefined} className={styles} {...props}>{loading ? <span aria-hidden className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-r-transparent" /> : leadingIcon}{children}</button>;
});
Button.displayName = "Button";
