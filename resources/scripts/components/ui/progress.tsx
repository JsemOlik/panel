import * as React from 'react';
import * as ProgressPrimitive from '@radix-ui/react-progress';
import { cn } from '@/lib/utils';

interface ProgressProps extends React.ComponentPropsWithoutRef<typeof ProgressPrimitive.Root> {
    // Classes for the filled part of the bar, e.g. to change its color.
    indicatorClassName?: string;
}

// Based on the shadcn/ui progress bar, using the panel's Tailwind palette instead of CSS variables.
const Progress = React.forwardRef<React.ElementRef<typeof ProgressPrimitive.Root>, ProgressProps>(
    ({ className, value, indicatorClassName, ...props }, ref) => (
        <ProgressPrimitive.Root
            ref={ref}
            value={value}
            className={cn('relative h-2 w-full overflow-hidden rounded-full bg-neutral-600', className)}
            {...props}
        >
            <ProgressPrimitive.Indicator
                className={cn('h-full w-full flex-1 bg-primary-500 transition-all duration-500', indicatorClassName)}
                style={{ transform: `translateX(-${100 - Math.min(100, Math.max(0, value || 0))}%)` }}
            />
        </ProgressPrimitive.Root>
    )
);
Progress.displayName = 'Progress';

export { Progress };
