import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';
import Spinner from '@/components/elements/Spinner';

// Based on the shadcn/ui button, using the panel's Tailwind palette instead of CSS variables.
const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-800 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-primary-500 text-white shadow-sm hover:bg-primary-600',
                destructive: 'bg-red-500 text-white shadow-sm hover:bg-red-600',
                'destructive-ghost': 'text-red-400 hover:bg-red-500 hover:text-white',
                outline:
                    'border border-neutral-600 bg-transparent text-neutral-100 shadow-sm hover:bg-neutral-600 hover:text-neutral-50',
                secondary: 'bg-neutral-600 text-neutral-100 shadow-sm hover:bg-neutral-500 hover:text-neutral-50',
                ghost: 'text-neutral-200 hover:bg-neutral-600 hover:text-neutral-50',
                link: 'text-primary-400 underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-9 px-4 py-2',
                sm: 'h-8 rounded-md px-3 text-xs',
                lg: 'h-10 rounded-lg px-8',
                icon: 'h-9 w-9',
                'icon-sm': 'h-8 w-8 rounded-md',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    // Renders the child element (e.g. a link) with the button styles instead of a <button>.
    asChild?: boolean;
    // Shows a spinner in place of the content and disables the button. Ignored with asChild.
    isLoading?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, asChild = false, isLoading = false, disabled, children, ...props }, ref) => {
        const classes = cn(buttonVariants({ variant, size, className }));

        if (asChild) {
            return (
                <Slot className={classes} ref={ref} {...props}>
                    {children}
                </Slot>
            );
        }

        return (
            <button className={cn(classes, 'relative')} ref={ref} disabled={disabled || isLoading} {...props}>
                {isLoading && (
                    <span className={'absolute inset-0 flex items-center justify-center'}>
                        <Spinner size={'small'} />
                    </span>
                )}
                {isLoading ? <span className={'invisible contents'}>{children}</span> : children}
            </button>
        );
    }
);
Button.displayName = 'Button';

export { Button, buttonVariants };
