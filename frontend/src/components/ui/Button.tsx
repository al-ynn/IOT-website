import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from "react";
import { cn } from "../../utils/cn";

export type ButtonVariant = "primary" | "secondary" | "outline" | "ghost" | "danger" | "warning";
export type ButtonSize = "compact" | "small" | "sm" | "default" | "lg";

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  leadingIcon?: ReactNode;
  trailingIcon?: ReactNode;
  asChild?: boolean;
}

/*
  Button system — subtle 3D press (from Pic 6) applied via inset highlight + soft shadow.
  Primary/warning use tonal solid; secondary uses raised surface; outline is precise;
  ghost is text-only for dense toolbars.
*/
const base =
  "relative inline-flex items-center justify-center gap-2 rounded-[8px] border font-medium " +
  "select-none whitespace-nowrap " +
  "transition-[color,background-color,border-color,box-shadow,transform] duration-150 " +
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)] focus-visible:ring-offset-0 " +
  "disabled:pointer-events-none disabled:opacity-55 " +
  "active:translate-y-[0.5px]";

const variants: Record<ButtonVariant, string> = {
  primary:
    "btn-sheen border-transparent bg-[var(--ds-primary)] text-white " +
    "shadow-[0_1px_0_rgb(255_255_255/.18)_inset,0_1px_2px_rgb(0_0_0/.22),0_6px_16px_-8px_rgb(59_130_246/.55)] " +
    "hover:bg-[var(--ds-primary-hover)] hover:shadow-[0_1px_0_rgb(255_255_255/.22)_inset,0_2px_4px_rgb(0_0_0/.24),0_10px_22px_-8px_rgb(59_130_246/.65),0_0_18px_-6px_var(--ds-chart-3)]",
  secondary:
    "border-[var(--ds-border)] bg-[var(--ds-surface-elevated)] text-[var(--ds-text)] " +
    "shadow-[0_1px_0_var(--ds-inset-highlight)_inset,0_1px_2px_var(--ds-inset-shadow)] " +
    "hover:border-[var(--ds-border-strong)] hover:bg-[color-mix(in_oklab,var(--ds-surface-elevated)_92%,var(--ds-primary)_8%)]",
  outline:
    "border-[var(--ds-border)] bg-transparent text-[var(--ds-text)] " +
    "hover:border-[var(--ds-primary-outline)] hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-primary)]",
  ghost:
    "border-transparent bg-transparent text-[var(--ds-text-muted)] " +
    "hover:bg-[var(--ds-primary-surface)] hover:text-[var(--ds-text)]",
  danger:
    "border-transparent bg-[var(--ds-danger)] text-white " +
    "shadow-[0_1px_0_rgb(255_255_255/.18)_inset,0_1px_2px_rgb(0_0_0/.22),0_6px_16px_-8px_rgb(239_68_68/.5)] " +
    "hover:brightness-110",
  warning:
    "border-transparent bg-[var(--ds-accent)] text-[#1F2937] " +
    "shadow-[0_1px_0_rgb(255_255_255/.35)_inset,0_1px_2px_rgb(0_0_0/.15),0_6px_16px_-8px_rgb(250_204_21/.55)] " +
    "hover:brightness-[1.06]",
};

const sizes: Record<ButtonSize, string> = {
  compact: "h-7 px-2.5 text-[12px] gap-1.5",
  small: "h-8 px-3 text-[12.5px]",
  sm: "h-8 px-3 text-[12.5px]",
  default: "h-9 px-3.5 text-sm",
  lg: "h-10 px-4 text-sm",
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  (
    {
      className,
      variant = "primary",
      size = "default",
      loading = false,
      leadingIcon,
      trailingIcon,
      disabled,
      children,
      asChild = false,
      type,
      ...props
    },
    ref,
  ) => {
    const styles = cn(base, variants[variant], sizes[size], className);
    const content = (
      <>
        {loading ? (
          <span
            aria-hidden
            className="h-3.5 w-3.5 shrink-0 animate-spin rounded-full border-2 border-current border-r-transparent"
          />
        ) : (
          leadingIcon && <span className="shrink-0 [&>svg]:h-4 [&>svg]:w-4">{leadingIcon}</span>
        )}
        {children != null && <span className="inline-flex items-center">{children}</span>}
        {trailingIcon && <span className="shrink-0 [&>svg]:h-4 [&>svg]:w-4">{trailingIcon}</span>}
      </>
    );
    if (asChild) return <span className={styles}>{content}</span>;
    return (
      <button
        ref={ref}
        type={type ?? "button"}
        disabled={disabled || loading}
        aria-busy={loading || undefined}
        className={styles}
        {...props}
      >
        {content}
      </button>
    );
  },
);
Button.displayName = "Button";
