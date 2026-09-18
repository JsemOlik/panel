import React from 'react';
import { HomeIcon, ViewGridIcon } from '@heroicons/react/outline';
import tw from 'twin.macro';
import { SidebarGroup, SidebarLink } from '@/components/elements/sidebar/Sidebar';

interface Props {
    collapsed: boolean;
}

// Top-level navigation for the dashboard shell (the account-wide pages, as opposed to a single
// server's console/settings, which is handled by `ServerSidebar`).
export default ({ collapsed }: Props) => (
    <nav aria-label={'Dashboard'} css={tw`flex flex-col p-3`}>
        <SidebarGroup collapsed={collapsed}>
            <SidebarLink to={'/'} exact label={'Dashboard'} icon={HomeIcon} collapsed={collapsed} />
            <SidebarLink to={'/areas'} label={'Areas'} icon={ViewGridIcon} collapsed={collapsed} />
        </SidebarGroup>
    </nav>
);
