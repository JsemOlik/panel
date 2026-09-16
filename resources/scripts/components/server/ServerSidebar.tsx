import React from 'react';
import { useStoreState } from 'easy-peasy';
import { ExternalLinkIcon } from '@heroicons/react/outline';
import tw from 'twin.macro';
import Can from '@/components/elements/Can';
import { SidebarGroup, SidebarLink } from '@/components/elements/sidebar/Sidebar';
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
