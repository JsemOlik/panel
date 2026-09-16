import * as React from 'react';
import { cn } from '@/lib/utils';
import { inputBaseClasses } from '@/components/ui/input';

export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
    // Convenience flag for aria-invalid; renders the field in its error state.
    hasError?: boolean;
}

// Based on the shadcn/ui textarea, using the panel's Tailwind palette instead of CSS variables.
const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
    ({ className, hasError, 'aria-invalid': ariaInvalid, ...props }, ref) => (
        <textarea
            className={cn(inputBaseClasses, 'min-h-[60px] py-2', className)}
            aria-invalid={hasError || ariaInvalid || undefined}
            ref={ref}
            {...props}
        />
    )
);
Textarea.displayName = 'Textarea';

export { Textarea };
