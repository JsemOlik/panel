import React, { lazy } from 'react';
import {
    ArchiveIcon,
    CalendarIcon,
    ClockIcon,
    CogIcon,
    DatabaseIcon,
    FolderIcon,
    GlobeAltIcon,
    PlayIcon,
    TerminalIcon,
    UsersIcon,
} from '@heroicons/react/outline';
import ServerConsole from '@/components/server/console/ServerConsoleContainer';
import DatabasesContainer from '@/components/server/databases/DatabasesContainer';
import ScheduleContainer from '@/components/server/schedules/ScheduleContainer';
import UsersContainer from '@/components/server/users/UsersContainer';
import BackupContainer from '@/components/server/backups/BackupContainer';
import NetworkContainer from '@/components/server/network/NetworkContainer';
import StartupContainer from '@/components/server/startup/StartupContainer';
import FileManagerContainer from '@/components/server/files/FileManagerContainer';
import SettingsContainer from '@/components/server/settings/SettingsContainer';
import AccountOverviewContainer from '@/components/dashboard/AccountOverviewContainer';
import AccountApiContainer from '@/components/dashboard/AccountApiContainer';
import AccountSSHContainer from '@/components/dashboard/ssh/AccountSSHContainer';
import ActivityLogContainer from '@/components/dashboard/activity/ActivityLogContainer';
import ServerActivityLogContainer from '@/components/server/ServerActivityLogContainer';

// Each of the router files is already code split out appropriately — so
// all of the items above will only be loaded in when that router is loaded.
//
// These specific lazy loaded routes are to avoid loading in heavy screens
// for the server dashboard when they're only needed for specific instances.
const FileEditContainer = lazy(() => import('@/components/server/files/FileEditContainer'));
const ScheduleEditContainer = lazy(() => import('@/components/server/schedules/ScheduleEditContainer'));

interface RouteDefinition {
    path: string;
    // If undefined is passed this route is still rendered into the router itself
    // but no navigation link is displayed in the sub-navigation menu.
    name: string | undefined;
    component: React.ComponentType;
    exact?: boolean;
}

interface ServerRouteDefinition extends RouteDefinition {
    permission: string | string[] | null;
    // The icon displayed next to the route name in the server sidebar.
    icon?: React.ComponentType<React.ComponentProps<'svg'>>;
    // The sidebar section this route is listed under, routes without one are listed first.
    group?: string;
}

interface Routes {
    // All of the routes available under "/account"
    account: RouteDefinition[];
    // All of the routes available under "/server/:id"
    server: ServerRouteDefinition[];
}

export default {
    account: [
        {
            path: '/',
            name: 'Account',
            component: AccountOverviewContainer,
            exact: true,
        },
        {
            path: '/api',
            name: 'API Credentials',
            component: AccountApiContainer,
        },
        {
            path: '/ssh',
            name: 'SSH Keys',
            component: AccountSSHContainer,
        },
        {
            path: '/activity',
            name: 'Activity',
            component: ActivityLogContainer,
        },
    ],
    server: [
        {
            path: '/',
            permission: null,
            name: 'Console',
            icon: TerminalIcon,
            component: ServerConsole,
            exact: true,
        },
        {
            path: '/files',
            permission: 'file.*',
            name: 'Files',
            icon: FolderIcon,
            group: 'Management',
            component: FileManagerContainer,
        },
        {
            path: '/files/:action(edit|new)',
            permission: 'file.*',
            name: undefined,
            component: FileEditContainer,
        },
        {
            path: '/databases',
            permission: 'database.*',
            name: 'Databases',
            icon: DatabaseIcon,
            group: 'Management',
            component: DatabasesContainer,
        },
        {
            path: '/schedules',
            permission: 'schedule.*',
            name: 'Schedules',
            icon: CalendarIcon,
            group: 'Management',
            component: ScheduleContainer,
        },
        {
            path: '/schedules/:id',
            permission: 'schedule.*',
            name: undefined,
            component: ScheduleEditContainer,
        },
        {
            path: '/users',
            permission: 'user.*',
            name: 'Users',
            icon: UsersIcon,
            group: 'Configuration',
            component: UsersContainer,
        },
        {
            path: '/backups',
            permission: 'backup.*',
            name: 'Backups',
            icon: ArchiveIcon,
            group: 'Management',
            component: BackupContainer,
        },
        {
            path: '/network',
            permission: 'allocation.*',
            name: 'Network',
            icon: GlobeAltIcon,
            group: 'Configuration',
            component: NetworkContainer,
        },
        {
            path: '/startup',
            permission: 'startup.*',
            name: 'Startup',
            icon: PlayIcon,
            group: 'Configuration',
            component: StartupContainer,
        },
        {
            path: '/settings',
            permission: ['settings.*', 'file.sftp'],
            name: 'Settings',
            icon: CogIcon,
            group: 'Configuration',
            component: SettingsContainer,
        },
        {
            path: '/activity',
            permission: 'activity.*',
            name: 'Activity',
            icon: ClockIcon,
            component: ServerActivityLogContainer,
        },
    ],
} as Routes;
