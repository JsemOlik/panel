import * as React from 'react';
import { Link } from 'react-router-dom';
import { MenuIcon, MoonIcon, SunIcon } from '@heroicons/react/outline';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import AccountMenu from '@/components/AccountMenu';
import { BrandIcon } from '@/components/elements/BrandLogo';
import { sidebarColors } from '@/components/elements/sidebar/Sidebar';
import { Button } from '@/components/ui/button';
import { setThemePreference, useTheme } from '@/lib/theme';

interface Props {
    onOpenSidebar?: () => void;
    sidebarOpen?: boolean;
    sidebarCollapsed?: boolean;
    onToggleSidebarCollapsed?: () => void;
}

// A panel-shaped icon, the left column filled in while the sidebar is expanded.
const SidebarIcon = ({ collapsed }: { collapsed?: boolean }) => (
    <svg viewBox={'0 0 24 24'} fill={'none'} stroke={'currentColor'} strokeWidth={1.75} aria-hidden={'true'}>
        <rect x={3} y={4} width={18} height={16} rx={2.5} />
        <path d={'M9 4v16'} />
        {!collapsed && <path d={'M5.5 8h1M5.5 11h1'} strokeLinecap={'round'} />}
    </svg>
);

export default ({ onOpenSidebar, sidebarOpen, sidebarCollapsed, onToggleSidebarCollapsed }: Props) => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data!.name);
    const { theme } = useTheme();

    const sidebarLabel = sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
    const themeLabel = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';

    return (
        <header
            className={'w-full border-b'}
            style={{ backgroundColor: sidebarColors.background, borderColor: sidebarColors.border }}
        >
            <div
                className={
                    'grid h-14 grid-cols-[1fr_auto_1fr] items-center gap-2 px-2 sm:px-4 md:grid-cols-[1fr_minmax(0,28rem)_1fr]'
                }
            >
                <div className={'flex items-center gap-2'}>
                    <div className={'flex items-center gap-2 lg:hidden'}>
                        <Button
                            variant={'ghost'}
                            size={'icon'}
                            onClick={onOpenSidebar}
                            aria-label={'Open sidebar'}
                            aria-expanded={sidebarOpen}
                            className={'text-neutral-300 [&_svg]:size-5'}
                        >
                            <MenuIcon />
                        </Button>
                        <Link to={'/'} className={'flex items-center no-underline'}>
                            <BrandIcon title={name} className={'block h-8 w-8'} />
                        </Link>
                    </div>
                    {onToggleSidebarCollapsed && (
                        <Tooltip placement={'bottom'} content={sidebarLabel}>
                            <Button
                                variant={'ghost'}
                                size={'icon'}
                                onClick={onToggleSidebarCollapsed}
                                aria-label={sidebarLabel}
                                aria-expanded={!sidebarCollapsed}
                                className={'hidden text-neutral-300 lg:inline-flex [&_svg]:size-5'}
                            >
                                <SidebarIcon collapsed={sidebarCollapsed} />
                            </Button>
                        </Tooltip>
                    )}
                </div>
                <div className={'flex justify-center'}>
                    <SearchContainer />
                </div>
                <div className={'flex items-center justify-end gap-1 sm:gap-2'}>
                    <Tooltip placement={'bottom'} content={themeLabel}>
                        <Button
                            variant={'ghost'}
                            size={'icon'}
                            onClick={() => setThemePreference(theme === 'dark' ? 'light' : 'dark')}
                            aria-label={themeLabel}
                            className={'text-neutral-300 [&_svg]:size-5'}
                        >
                            {theme === 'dark' ? <MoonIcon /> : <SunIcon />}
                        </Button>
                    </Tooltip>
                    <AccountMenu />
                </div>
            </div>
        </header>
    );
};
