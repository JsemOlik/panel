import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import {
    ChevronDownIcon,
    ClockIcon,
    CogIcon,
    ColorSwatchIcon,
    ExternalLinkIcon,
    LogoutIcon,
    ViewGridIcon,
} from '@heroicons/react/outline';
import { useStoreState } from '@/state/hooks';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const initials = (value: string) =>
    value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

export default () => {
    const user = useStoreState((state) => state.user.data!);
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const name = [user.nameFirst, user.nameLast].filter(Boolean).join(' ') || user.username;

    const onLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <>
            <SpinnerOverlay visible={isLoggingOut} fixed />
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger
                    aria-label={'Account menu'}
                    className={
                        'group flex h-9 items-center gap-2 rounded-lg px-1.5 text-sm font-medium text-neutral-100 transition-colors hover:bg-neutral-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 data-[state=open]:bg-neutral-600 sm:pr-2'
                    }
                >
                    <span
                        aria-hidden={'true'}
                        className={
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/15 text-xs font-semibold text-primary-400'
                        }
                    >
                        {initials(name)}
                    </span>
                    <span className={'hidden max-w-[10rem] truncate sm:block'}>{name}</span>
                    <ChevronDownIcon
                        aria-hidden={'true'}
                        className={
                            'hidden h-4 w-4 text-neutral-400 transition-transform duration-150 group-data-[state=open]:rotate-180 sm:block'
                        }
                    />
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align={'end'}
                    className={'w-60 [&_[role=menuitem]_svg]:h-4 [&_[role=menuitem]_svg]:w-4'}
                >
                    <DropdownMenuLabel className={'normal-case'}>
                        <span className={'block truncate text-sm font-medium text-neutral-50'}>{name}</span>
                        <span className={'block truncate text-xs font-normal text-neutral-400'}>{user.email}</span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {/*<DropdownMenuItem asChild>
                        <Link to={'/'}>
                            <ViewGridIcon />
                            Dashboard
                        </Link>
                    </DropdownMenuItem>*/}
                    <DropdownMenuItem asChild>
                        <Link to={'/account'}>
                            <CogIcon />
                            Settings
                        </Link>
                    </DropdownMenuItem>
                    {/*<DropdownMenuItem asChild>
                        <Link to={'/account/appearance'}>
                            <ColorSwatchIcon />
                            Appearance
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link to={'/account/activity'}>
                            <ClockIcon />
                            Activity
                        </Link>
                    </DropdownMenuItem>*/}
                    {user.rootAdmin && (
                        <DropdownMenuItem asChild>
                            <a href={'/admin'}>
                                <ExternalLinkIcon />
                                Admin
                            </a>
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem variant={'destructive'} onSelect={onLogout}>
                        <LogoutIcon />
                        Sign out
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );
};
