import type {HTMLAttributes} from "react";import {cn} from "../../utils/cn";
export function PageTitle({className,...props}:HTMLAttributes<HTMLHeadingElement>){return <h1 className={cn("text-2xl font-semibold tracking-tight text-[var(--ds-text)] sm:text-[28px]",className)} {...props}/>;}
export function SectionTitle({className,...props}:HTMLAttributes<HTMLHeadingElement>){return <h2 className={cn("text-base font-semibold text-[var(--ds-text)]",className)} {...props}/>;}
export function BodyText({className,...props}:HTMLAttributes<HTMLParagraphElement>){return <p className={cn("text-sm leading-5 text-[var(--ds-text-muted)]",className)} {...props}/>;}
export function Caption({className,...props}:HTMLAttributes<HTMLParagraphElement>){return <p className={cn("text-xs leading-4 text-[var(--ds-text-subtle)]",className)} {...props}/>;}
export function Label({className,...props}:HTMLAttributes<HTMLSpanElement>){return <span className={cn("text-xs font-medium text-[var(--ds-text-muted)]",className)} {...props}/>;}
