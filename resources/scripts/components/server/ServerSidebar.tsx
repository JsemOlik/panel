import React, { useEffect, useState } from 'react';
import { NavLink, matchPath, useLocation } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faAngleDoubleLeft,
    faAngleDoubleRight,
    faBars,
    faExternalLinkAlt,
    faTimes,
} from '@fortawesome/free-solid-svg-icons';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import Can from '@/components/elements/Can';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { ServerContext } from '@/state/server';
import routes from '@/routers/routes';

interface Props {
    // Resolves a route path into a full path (url = false) or URL (url = true) for this server.
    to: (value: string, url?: boolean) => string;
}

const accent = '#8a4cf5';
const collapsedStorageKey = 'server_sidebar_collapsed';

const Links = styled.nav<{ $collapsed: boolean }>`
    ${tw`flex flex-col gap-1 p-2`};

    & a {
        ${tw`flex items-center gap-3 rounded px-3 py-2 text-sm text-neutral-300 no-underline whitespace-nowrap transition-colors duration-150`};
        ${(props) => props.$collapsed && tw`justify-center px-0`};

        &:hover {
            ${tw`bg-neutral-700 text-neutral-50`};
        }

        &.active {
            ${tw`text-neutral-50`};
            background-color: rgba(138, 76, 245, 0.18);
            box-shadow: inset 3px 0 ${accent};

            & svg {
                color: ${accent};
            }
        }

        & svg {
            ${tw`w-4 flex-shrink-0 text-neutral-400`};
        }
    }
`;

const readCollapsed = (): boolean => {
    try {
        return localStorage.getItem(collapsedStorageKey) === '1';
    } catch {
        return false;
    }
};

const SidebarLinks = ({ to, collapsed = false }: Props & { collapsed?: boolean }) => {
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);

    // When collapsed only the icons are visible, so show each page name in a tooltip instead.
    const withTooltip = (label: string, element: React.ReactElement) => (
        <Tooltip placement={'right'} content={label} disabled={!collapsed}>
            {element}
        </Tooltip>
    );

    return (
        <Links $collapsed={collapsed}>
            {routes.server
                .filter((route) => !!route.name)
                .map((route) => {
                    const link = withTooltip(
                        route.name!,
                        <NavLink to={to(route.path, true)} exact={route.exact} aria-label={route.name}>
                            {route.icon && <FontAwesomeIcon icon={route.icon} fixedWidth />}
                            {!collapsed && route.name}
                        </NavLink>
                    );

                    return route.permission ? (
                        <Can key={route.path} action={route.permission} matchAny>
                            {link}
                        </Can>
                    ) : (
                        <React.Fragment key={route.path}>{link}</React.Fragment>
                    );
                })}
            {rootAdmin && (
                <>
                    <hr css={tw`my-1 border-neutral-700`} />
                    {withTooltip(
                        'View in Admin',
                        <a
                            href={`/admin/servers/view/${serverId}`}
                            target={'_blank'}
                            rel={'noreferrer'}
                            aria-label={'View in Admin'}
                        >
                            <FontAwesomeIcon icon={faExternalLinkAlt} fixedWidth />
                            {!collapsed && 'View in Admin'}
                        </a>
                    )}
                </>
            )}
        </Links>
    );
};

const ServerName = () => {
    const name = ServerContext.useStoreState((state) => state.server.data?.name);

    return (
        <div css={tw`min-w-0`}>
            <p css={tw`text-2xs uppercase tracking-wide text-neutral-400`}>Server</p>
            <p css={tw`mt-0.5 truncate font-header font-medium text-neutral-50`} title={name}>
                {name}
            </p>
        </div>
    );
};

export default ({ to }: Props) => {
    const location = useLocation();
    const [open, setOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(readCollapsed);

    const current = routes.server
        .filter((route) => !!route.name)
        .find((route) => matchPath(location.pathname, { path: to(route.path), exact: route.exact }));

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

    return (
        <>
            {/* Mobile & tablet: a bar showing the current page with a button opening the drawer. */}
            <div css={tw`lg:hidden sticky top-0 z-30 bg-neutral-900 shadow`}>
                <div css={tw`flex items-center gap-2 px-4 h-12`}>
                    <button
                        type={'button'}
                        onClick={() => setOpen(true)}
                        aria-label={'Open server navigation'}
                        aria-expanded={open}
                        css={tw`-ml-2 p-2 text-neutral-300 hover:text-neutral-50`}
                    >
                        <FontAwesomeIcon icon={faBars} fixedWidth />
                    </button>
                    <span css={tw`flex items-center gap-2 text-sm text-neutral-100`}>
                        {current?.icon && <FontAwesomeIcon icon={current.icon} fixedWidth css={{ color: accent }} />}
                        {current?.name}
                    </span>
                </div>
            </div>
            <div
                onClick={() => setOpen(false)}
                aria-hidden={'true'}
                css={[
                    tw`lg:hidden fixed inset-0 z-40 bg-black bg-opacity-60 transition-opacity duration-150`,
                    open ? tw`opacity-100` : tw`opacity-0 pointer-events-none`,
                ]}
            />
            <aside
                aria-label={'Server navigation'}
                style={{ maxWidth: '85vw' }}
                css={[
                    tw`lg:hidden fixed inset-y-0 left-0 z-50 w-64 overflow-y-auto bg-neutral-900 shadow-lg transform transition-transform duration-150`,
                    open ? tw`translate-x-0` : tw`-translate-x-full invisible`,
                ]}
            >
                <div css={tw`flex items-start justify-between gap-2 pl-5 pr-2 pt-4 pb-2`}>
                    <ServerName />
                    <button
                        type={'button'}
                        onClick={() => setOpen(false)}
                        aria-label={'Close server navigation'}
                        css={tw`p-2 text-neutral-300 hover:text-neutral-50`}
                    >
                        <FontAwesomeIcon icon={faTimes} fixedWidth />
                    </button>
                </div>
                <SidebarLinks to={to} />
            </aside>

            {/* Desktop: a full-height, collapsible sidebar on the left that stays in view while scrolling. */}
            <aside
                aria-label={'Server navigation'}
                css={[
                    tw`hidden lg:block flex-shrink-0 self-stretch overflow-hidden bg-neutral-900 border-r border-neutral-700 transition-all duration-150`,
                    collapsed ? tw`w-16` : tw`w-52`,
                ]}
            >
                <div css={tw`sticky top-0 overflow-y-auto overflow-x-hidden`} style={{ maxHeight: '100vh' }}>
                    <div
                        css={[
                            tw`flex items-start gap-2 pt-4 pb-2`,
                            collapsed ? tw`justify-center px-2` : tw`justify-between pl-4 pr-2`,
                        ]}
                    >
                        {!collapsed && <ServerName />}
                        <Tooltip placement={'right'} content={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}>
                            <button
                                type={'button'}
                                onClick={toggleCollapsed}
                                aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                                aria-expanded={!collapsed}
                                css={tw`p-2 rounded text-neutral-400 hover:text-neutral-50 hover:bg-neutral-700`}
                            >
                                <FontAwesomeIcon icon={collapsed ? faAngleDoubleRight : faAngleDoubleLeft} fixedWidth />
                            </button>
                        </Tooltip>
                    </div>
                    <SidebarLinks to={to} collapsed={collapsed} />
                </div>
            </aside>
        </>
    );
};
