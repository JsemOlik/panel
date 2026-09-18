import React from 'react';
import tw, { TwStyle } from 'twin.macro';
import styled from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBan,
    faCheckCircle,
    faCircleNotch,
    faClock,
    faExclamationTriangle,
    faTimesCircle,
} from '@fortawesome/free-solid-svg-icons';
import { WallboardServerStatus } from '@/api/wallboard/getWallboard';
import { bytesToString } from '@/lib/formatters';

/**
 * Every visual state a tile can render, resolved once here rather than scattered across JSX —
 * each carries a color (never the only signal) AND a distinct icon AND a text label, so the
 * board stays readable for colorblind viewers and still makes sense as a black & white photo.
 */
type Visual = {
    label: string;
    icon: typeof faCheckCircle;
    color: 'green' | 'yellow' | 'red' | 'gray';
    spin?: boolean;
};

const resolveVisual = (server: WallboardServerStatus): Visual => {
    if (server.unreachable) {
        return { label: 'Unreachable', icon: faExclamationTriangle, color: 'gray' };
    }

    if (server.isSuspended || server.state === 'suspended') {
        return { label: 'Suspended', icon: faBan, color: 'gray' };
    }

    switch (server.state) {
        case 'running':
            return { label: 'Online', icon: faCheckCircle, color: 'green' };
        case 'starting':
            return { label: 'Starting', icon: faCircleNotch, color: 'yellow', spin: true };
        case 'stopping':
            return { label: 'Stopping', icon: faCircleNotch, color: 'yellow', spin: true };
        case 'offline':
            return { label: 'Offline', icon: faTimesCircle, color: 'red' };
        default:
            return { label: 'Unknown', icon: faExclamationTriangle, color: 'gray' };
    }
};

const colorClasses: Record<Visual['color'], { text: TwStyle; border: TwStyle; bg: TwStyle }> = {
    green: { text: tw`text-green-400`, border: tw`border-green-500/60`, bg: tw`bg-green-500/10` },
    yellow: { text: tw`text-yellow-400`, border: tw`border-yellow-500/60`, bg: tw`bg-yellow-500/10` },
    red: { text: tw`text-red-400`, border: tw`border-red-500/60`, bg: tw`bg-red-500/10` },
    gray: { text: tw`text-neutral-400`, border: tw`border-neutral-500/60`, bg: tw`bg-neutral-500/10` },
};

const Tile = styled.div<{ $muted: boolean }>`
    ${tw`rounded-lg border-2 p-4 flex flex-col justify-between min-h-[9rem] transition-opacity duration-500`};
    ${(props) => (props.$muted ? tw`opacity-70` : '')};
`;

const formatLastSeen = (iso: string | null): string => {
    if (!iso) return 'never';

    const diffMs = Date.now() - new Date(iso).getTime();
    const minutes = Math.max(0, Math.round(diffMs / 60000));

    if (minutes < 1) return 'just now';
    if (minutes === 1) return '1 min ago';
    if (minutes < 60) return `${minutes} min ago`;

    const hours = Math.round(minutes / 60);

    return hours === 1 ? '1 hr ago' : `${hours} hrs ago`;
};

export default ({ server }: { server: WallboardServerStatus }) => {
    const visual = resolveVisual(server);
    const colors = colorClasses[visual.color];

    return (
        <Tile $muted={server.stale || server.unreachable} css={[tw`bg-neutral-700`, colors.border, colors.bg]}>
            <div css={tw`flex items-start justify-between gap-2`}>
                <div css={tw`min-w-0`}>
                    <p css={tw`text-xl lg:text-2xl font-bold text-neutral-50 break-words leading-tight`}>
                        {server.name}
                    </p>
                    <p css={tw`text-xs lg:text-sm text-neutral-400 uppercase tracking-wide mt-1`}>
                        {server.role === 'proxy' ? 'Proxy' : 'Server'}
                    </p>
                </div>
                {server.isNodeUnderMaintenance && (
                    <FontAwesomeIcon
                        icon={faExclamationTriangle}
                        css={tw`text-yellow-400 text-lg flex-shrink-0`}
                        title={'Node under maintenance'}
                    />
                )}
            </div>

            <div>
                <div css={tw`flex items-center gap-2`}>
                    <FontAwesomeIcon
                        icon={visual.icon}
                        spin={visual.spin}
                        css={[tw`text-2xl lg:text-3xl`, colors.text]}
                    />
                    <span css={[tw`text-lg lg:text-xl font-semibold`, colors.text]}>{visual.label}</span>
                </div>

                {server.stale && (
                    <p css={tw`flex items-center gap-1 text-xs lg:text-sm text-yellow-400 mt-2`}>
                        <FontAwesomeIcon icon={faClock} />
                        Last known — {formatLastSeen(server.lastSeenAt)}
                    </p>
                )}

                {server.unreachable && (
                    <p css={tw`text-xs lg:text-sm text-neutral-400 mt-2`}>
                        Last seen {formatLastSeen(server.lastSeenAt)}
                    </p>
                )}

                {server.live && server.cpuAbsolute !== null && server.memoryBytes !== null && (
                    <p css={tw`text-xs lg:text-sm text-neutral-400 mt-2`}>
                        {server.cpuAbsolute.toFixed(1)}% CPU · {bytesToString(server.memoryBytes)} RAM
                    </p>
                )}
            </div>
        </Tile>
    );
};
