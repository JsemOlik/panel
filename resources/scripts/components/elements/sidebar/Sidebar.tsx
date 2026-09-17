import React, { useState } from 'react';
import { NavLink } from 'react-router-dom';
import { ChevronDownIcon } from '@heroicons/react/outline';
import tw from 'twin.macro';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export const sidebarColors = {
    background: 'var(--chrome-background)',
    foreground: 'rgb(var(--color-gray-200))',
    muted: 'rgb(var(--color-gray-400))',
    border: 'var(--chrome-border)',
    hover: 'var(--chrome-hover)',
};

type Icon = React.ComponentType<React.ComponentProps<'svg'>>;

interface LinkProps {
    label: string;
    icon?: Icon;
    collapsed?: boolean;
}

// Plain ghost buttons laid out as a list; the active route is filled with the primary color.
const linkClasses =
    'w-full justify-start [&.active]:bg-primary-500 [&.active]:text-white [&.active]:hover:bg-primary-600';

// A link within the sidebar. When the sidebar is collapsed only the icon is shown, with the
// label moved into a tooltip.
export const SidebarLink = ({
    label,
    icon: Icon,
    collapsed,
    ...props
}: LinkProps & ({ to: string; exact?: boolean; href?: never } | { href: string; to?: never; exact?: never })) => {
    const content = (
        <>
            {Icon && <Icon aria-hidden={'true'} />}
            {!collapsed && <span className={'truncate'}>{label}</span>}
        </>
    );

    return (
        <Tooltip placement={'right'} content={label} disabled={!collapsed}>
            <Button asChild variant={'ghost'} className={cn(linkClasses, collapsed && 'justify-center px-0')}>
                {props.to !== undefined ? (
                    <NavLink to={props.to} exact={props.exact} aria-label={label}>
                        {content}
                    </NavLink>
                ) : (
                    <a href={props.href} target={'_blank'} rel={'noreferrer'} aria-label={label}>
                        {content}
                    </a>
                )}
            </Button>
        </Tooltip>
    );
};

interface GroupProps {
    label?: string;
    collapsed?: boolean;
    children: React.ReactNode;
}

// A titled, collapsible section of sidebar links. Sections without a label simply list their links.
export const SidebarGroup = ({ label, collapsed, children }: GroupProps) => {
    const [open, setOpen] = useState(true);

    if (!label) {
        return <div css={tw`flex flex-col gap-1`}>{children}</div>;
    }

    // In the icon-only sidebar there is no room for titles, so separate the sections with a line.
    if (collapsed) {
        return (
            <div css={tw`flex flex-col gap-1`}>
                <hr css={tw`mx-2 my-2`} style={{ borderColor: sidebarColors.border }} />
                {children}
            </div>
        );
    }

    return (
        <div css={tw`flex flex-col gap-1 mt-5`}>
            <Button
                variant={'ghost'}
                size={'sm'}
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                className={
                    'h-7 justify-between px-3 text-xs font-semibold uppercase tracking-wider text-neutral-400 hover:bg-transparent'
                }
            >
                {label}
                <ChevronDownIcon
                    aria-hidden={'true'}
                    className={cn('transform transition-transform duration-150', !open && '-rotate-90')}
                />
            </Button>
            {open && children}
        </div>
    );
};
