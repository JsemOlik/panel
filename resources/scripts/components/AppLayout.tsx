import React, { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faTimes } from '@fortawesome/free-solid-svg-icons';
import { ChevronDoubleLeftIcon, ChevronDoubleRightIcon } from '@heroicons/react/outline';
import tw from 'twin.macro';
import NavigationBar from '@/components/NavigationBar';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { ApplicationStore } from '@/state';
import { sidebarColors } from '@/components/elements/sidebar/Sidebar';
import { Button } from '@/components/ui/button';
import { BrandIcon, BrandLogo } from '@/components/elements/BrandLogo';

interface Props {
    // Renders the navigation shown in the sidebar. The mobile drawer is never collapsed.
    sidebar?: (collapsed: boolean) => React.ReactNode;
    // Rendered at the bottom of the sidebar, below the navigation.
    sidebarFooter?: (collapsed: boolean) => React.ReactNode;
    children: React.ReactNode;
}

const collapsedStorageKey = 'sidebar_collapsed';

const readCollapsed = (): boolean => {
    try {
        return localStorage.getItem(collapsedStorageKey) === '1';
    } catch {
        return false;
    }
};

const Logo = ({ collapsed = false }: { collapsed?: boolean }) => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data!.name);

    return (
        <Link to={'/'} css={tw`flex items-center no-underline`}>
            {collapsed ? (
                <BrandIcon title={name} css={tw`block w-8 h-8`} />
            ) : (
                <BrandLogo title={name} css={tw`block h-8 w-auto`} />
            )}
        </Link>
    );
};

export default ({ sidebar, sidebarFooter, children }: Props) => {
    const location = useLocation();
    const [open, setOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(readCollapsed);

    const toggleCollapsed = () => {
        setCollapsed((value) => {
            try {
                localStorage.setItem(collapsedStorageKey, value ? '0' : '1');
            } catch {
                // Storage may be unavailable (e.g. private browsing), the preference just won't persist.
            }

            return !value;
        });
    };

    // Close the mobile drawer whenever the user navigates to another page.
    useEffect(() => {
        setOpen(false);
    }, [location.pathname]);

    useEffect(() => {
        if (!open) return;

        const onKeyDown = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        const overflow = document.body.style.overflow;

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.style.overflow = overflow;
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    const collapseToggle = (
        <Tooltip placement={'right'} content={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}>
            <Button
                variant={'ghost'}
                size={'icon'}
                onClick={toggleCollapsed}
                aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                aria-expanded={!collapsed}
                className={'text-neutral-400 [&_svg]:size-5'}
            >
                {collapsed ? <ChevronDoubleRightIcon /> : <ChevronDoubleLeftIcon />}
            </Button>
        </Tooltip>
    );

    return (
        <div css={tw`flex min-h-screen`}>
            {/* Mobile & tablet: a drawer sliding in from the left, opened from the top bar. */}
            <div
                onClick={() => setOpen(false)}
                aria-hidden={'true'}
                css={[
                    tw`lg:hidden fixed inset-0 z-40 bg-black bg-opacity-60 transition-opacity duration-150`,
                    open ? tw`opacity-100` : tw`opacity-0 pointer-events-none`,
                ]}
            />
            <aside
                aria-label={'Sidebar'}
                style={{ maxWidth: '85vw', backgroundColor: sidebarColors.background }}
                css={[
                    tw`lg:hidden fixed inset-y-0 left-0 z-50 flex flex-col w-72 shadow-lg transform transition-transform duration-150`,
                    open ? tw`translate-x-0` : tw`-translate-x-full invisible`,
                ]}
            >
                <div
                    css={tw`flex flex-shrink-0 items-center justify-between h-14 pl-5 pr-3 border-b`}
                    style={{ borderColor: sidebarColors.border }}
                >
                    <Logo />
                    <Button
                        variant={'ghost'}
                        size={'icon'}
                        onClick={() => setOpen(false)}
                        aria-label={'Close sidebar'}
                        className={'text-neutral-300'}
                    >
                        <FontAwesomeIcon icon={faTimes} fixedWidth />
                    </Button>
                </div>
                <div css={tw`flex-1 overflow-y-auto`}>{sidebar?.(false)}</div>
                {sidebarFooter && (
                    <div css={tw`flex-shrink-0 border-t p-3`} style={{ borderColor: sidebarColors.border }}>
                        {sidebarFooter(false)}
                    </div>
                )}
            </aside>

            {/* Desktop: a full-height, collapsible sidebar that stays in view while scrolling. */}
            <aside
                aria-label={'Sidebar'}
                style={{ backgroundColor: sidebarColors.background, borderColor: sidebarColors.border }}
                css={[
                    tw`hidden lg:flex flex-col flex-shrink-0 self-start sticky top-0 h-screen overflow-hidden border-r transition-all duration-150`,
                    collapsed ? tw`w-20` : tw`w-72`,
                ]}
            >
                <div
                    css={[
                        tw`flex flex-shrink-0 items-center h-14 border-b`,
                        collapsed ? tw`justify-center` : tw`justify-between pl-5 pr-3`,
                    ]}
                    style={{ borderColor: sidebarColors.border }}
                >
                    <Logo collapsed={collapsed} />
                    {!collapsed && collapseToggle}
                </div>
                <div css={tw`flex-1 overflow-y-auto overflow-x-hidden`}>{sidebar?.(collapsed)}</div>
                {(sidebarFooter || collapsed) && (
                    <div
                        css={tw`flex flex-shrink-0 flex-col items-stretch gap-2 border-t p-3`}
                        style={{ borderColor: sidebarColors.border }}
                    >
                        {sidebarFooter?.(collapsed)}
                        {collapsed && <div css={tw`flex justify-center`}>{collapseToggle}</div>}
                    </div>
                )}
            </aside>

            <div css={tw`flex flex-col flex-1 min-w-0`}>
                <NavigationBar onOpenSidebar={() => setOpen(true)} sidebarOpen={open} />
                <main css={tw`flex-1 min-w-0`}>{children}</main>
            </div>
        </div>
    );
};
