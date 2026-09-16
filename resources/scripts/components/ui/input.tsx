import * as React from 'react';
import { cn } from '@/lib/utils';

// Shared styles for single and multi-line text fields. Invalid fields are marked with
// `aria-invalid`, which switches the border and focus ring to red.
export const inputBaseClasses =
    'flex w-full min-w-0 rounded-lg border border-neutral-500 bg-neutral-900/40 px-3 text-sm text-neutral-100 shadow-sm transition-colors placeholder:text-neutral-400 hover:border-neutral-400 focus-visible:border-primary-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/40 disabled:cursor-not-allowed disabled:opacity-50 read-only:hover:border-neutral-500 aria-[invalid=true]:border-red-500 aria-[invalid=true]:focus-visible:ring-red-500/40';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    // Convenience flag for aria-invalid; renders the field in its error state.
    hasError?: boolean;
}

// Based on the shadcn/ui input, using the panel's Tailwind palette instead of CSS variables.
const Input = React.forwardRef<HTMLInputElement, InputProps>(
    ({ className, type = 'text', hasError, 'aria-invalid': ariaInvalid, ...props }, ref) => (
        <input
            type={type}
            className={cn(
                inputBaseClasses,
                'h-9 py-1 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-neutral-100',
                className
            )}
            aria-invalid={hasError || ariaInvalid || undefined}
            ref={ref}
            {...props}
        />
    )
);
Input.displayName = 'Input';

export { Input };
