import React from 'react';
import Icon from '@/components/elements/Icon';
import { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import useFitText from 'use-fit-text';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

export type StatTone = 'warning' | 'danger';

interface StatBlockProps {
    title: string;
    copyOnClick?: string;
    tone?: StatTone;
    icon: IconDefinition;
    // Usage as a percentage of the limit. No bar is shown when undefined (e.g. unlimited).
    progress?: number;
    children: React.ReactNode;
    className?: string;
}

const toneClasses: Record<StatTone | 'default', { icon: string; bar: string; indicator: string }> = {
    default: { icon: 'bg-neutral-600 text-neutral-300', bar: 'bg-neutral-600', indicator: 'bg-primary-500' },
    warning: { icon: 'bg-yellow-500/15 text-yellow-400', bar: 'bg-yellow-500', indicator: 'bg-yellow-500' },
    danger: { icon: 'bg-red-500/15 text-red-400', bar: 'bg-red-500', indicator: 'bg-red-500' },
};

export default ({ title, copyOnClick, icon, tone, progress, className, children }: StatBlockProps) => {
    const { fontSize, ref } = useFitText({ minFontSize: 8, maxFontSize: 500 });
    const classes = toneClasses[tone || 'default'];

    return (
        <CopyOnClick text={copyOnClick}>
            <Card
                className={cn(
                    'relative flex items-center gap-4 overflow-hidden px-3 py-2 md:p-3 lg:p-4',
                    'col-span-3 md:col-span-2 lg:col-span-6',
                    className
                )}
            >
                {/* On small screens the icon is hidden, so the tone is shown as a bar on the left edge. */}
                <div className={cn('absolute inset-y-0 left-0 w-1 sm:hidden', classes.bar)} />
                <div
                    className={cn(
                        'hidden h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg transition-colors duration-500 sm:flex',
                        classes.icon
                    )}
                >
                    <Icon icon={icon} className={'h-4 w-4'} />
                </div>
                <div className={'flex w-full min-w-0 flex-col justify-center'}>
                    <p className={'text-xs font-medium leading-tight text-neutral-400'}>{title}</p>
                    <div
                        ref={ref}
                        className={'h-[1.75rem] w-full truncate font-semibold text-neutral-50'}
                        style={{ fontSize }}
                    >
                        {children}
                    </div>
                    {progress !== undefined && (
                        <Progress value={progress} className={'mt-1.5 h-1.5'} indicatorClassName={classes.indicator} />
                    )}
                </div>
            </Card>
        </CopyOnClick>
    );
};
