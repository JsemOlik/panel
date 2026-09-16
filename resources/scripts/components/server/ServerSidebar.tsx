import React from 'react';
import { Link } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { ExternalLinkIcon, SelectorIcon, ServerIcon } from '@heroicons/react/outline';
import tw from 'twin.macro';
import Can from '@/components/elements/Can';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { SidebarGroup, SidebarLink, sidebarColors } from '@/components/elements/sidebar/Sidebar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ServerContext } from '@/state/server';
import routes from '@/routers/routes';

interface Props {
    // Resolves a route path into a full path (url = false) or URL (url = true) for this server.
    to: (value: string, url?: boolean) => string;
    collapsed: boolean;
}

export default ({ to, collapsed }: Props) => {
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);

    // Keep the order routes are defined in, with ungrouped routes listed first.
    const groups = routes.server
        .filter((route) => !!route.name)
        .reduce<{ label?: string; routes: typeof routes.server }[]>((groups, route) => {
            const group = groups.find((g) => g.label === route.group);
            group ? group.routes.push(route) : groups.push({ label: route.group, routes: [route] });

            return groups;
        }, [])
        .sort((a, b) => Number(!!a.label) - Number(!!b.label));

    return (
        <nav aria-label={'Server'} css={tw`flex flex-col p-3`}>
            {groups.map((group) => (
                <SidebarGroup key={group.label || 'default'} label={group.label} collapsed={collapsed}>
                    {group.routes.map((route) => {
                        const link = (
                            <SidebarLink
                                to={to(route.path, true)}
                                exact={route.exact}
                                label={route.name!}
                                icon={route.icon}
                                collapsed={collapsed}
                            />
                        );

                        return route.permission ? (
                            <Can key={route.path} action={route.permission} matchAny>
                                {link}
                            </Can>
                        ) : (
                            <React.Fragment key={route.path}>{link}</React.Fragment>
                        );
                    })}
                </SidebarGroup>
            ))}
            {rootAdmin && (
                <SidebarGroup label={'Administration'} collapsed={collapsed}>
                    <SidebarLink
                        href={`/admin/servers/view/${serverId}`}
                        label={'View in Admin'}
                        icon={ExternalLinkIcon}
                        collapsed={collapsed}
                    />
                </SidebarGroup>
            )}
        </nav>
    );
};

// Shows the server being managed at the bottom of the sidebar, linking back to the server list.
export const ServerSidebarFooter = ({ collapsed }: { collapsed: boolean }) => {
    const name = ServerContext.useStoreState((state) => state.server.data?.name);
    const node = ServerContext.useStoreState((state) => state.server.data?.node);

    const avatar = (
        <span
            css={tw`flex flex-shrink-0 items-center justify-center w-10 h-10 rounded-lg text-neutral-50`}
            style={{ backgroundColor: sidebarColors.accent }}
        >
            <ServerIcon className={'!size-5'} aria-hidden={'true'} />
        </span>
    );

    return (
        <Tooltip placement={'right'} content={'Switch server'} disabled={!collapsed}>
            <Button
                asChild
                variant={'ghost'}
                className={cn('h-auto w-full justify-start gap-3 p-2', collapsed && 'justify-center')}
            >
                <Link to={'/'} aria-label={'Switch server'}>
                    {avatar}
                    {!collapsed && (
                        <>
                            <span css={tw`flex-1 min-w-0`}>
                                <span css={tw`block truncate text-sm font-semibold text-neutral-50`} title={name}>
                                    {name}
                                </span>
                                <span css={tw`block truncate text-xs`} style={{ color: sidebarColors.muted }}>
                                    {node}
                                </span>
                            </span>
                            <SelectorIcon style={{ color: sidebarColors.muted }} />
                        </>
                    )}
                </Link>
            </Button>
        </Tooltip>
    );
};
