import React, { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import useSWR from 'swr';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faServer } from '@fortawesome/free-solid-svg-icons';
import getArea, { Area, AreaMember } from '@/api/areas/getArea';
import getServerResourceUsage, { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import GreyRowBox from '@/components/elements/GreyRowBox';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import AreaPowerActions from '@/components/areas/AreaPowerActions';
import AreaCommandDialog from '@/components/areas/AreaCommandDialog';
import useFlash from '@/plugins/useFlash';

const MemberRowBox = styled(GreyRowBox)<{ $status: ServerPowerState | undefined }>`
    ${tw`grid grid-cols-12 gap-4 relative`};

    & .status-bar {
        ${tw`w-2 bg-red-500 absolute right-0 z-20 rounded-full m-1 opacity-50 transition-all duration-150`};
        height: calc(100% - 0.5rem);

        ${({ $status }) =>
            !$status || $status === 'offline'
                ? tw`bg-red-500`
                : $status === 'running'
                ? tw`bg-green-500`
                : tw`bg-yellow-500`};
    }

    &:hover .status-bar {
        ${tw`opacity-75`};
    }
`;

const RoleBadge = ({ role }: { role: AreaMember['role'] }) => (
    <span
        css={tw`rounded-md px-2 py-1 text-xs uppercase tracking-wide`}
        className={role === 'proxy' ? 'bg-primary-500 text-white' : 'bg-neutral-500 text-neutral-100'}
    >
        {role === 'proxy' ? 'Proxy' : 'Game server'}
    </span>
);

type Timer = ReturnType<typeof setInterval>;

// Mirrors the per-server status polling pattern in `components/dashboard/ServerRow.tsx`. Areas
// are capped at a small handful of members, so one polling timer per member row is fine here —
// a shared/batched status endpoint is a future optimization if this pattern needs to scale to
// showing many areas at once (e.g. a wallboard).
const MemberRow = ({ member, className }: { member: AreaMember; className?: string }) => {
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const [isSuspended, setIsSuspended] = useState(member.status === 'suspended');
    const [stats, setStats] = useState<ServerStats | null>(null);

    const getStats = () =>
        getServerResourceUsage(member.uuid)
            .then((data) => setStats(data))
            .catch((error) => console.error(error));

    useEffect(() => {
        setIsSuspended(stats?.isSuspended || member.status === 'suspended');
    }, [stats?.isSuspended, member.status]);

    useEffect(() => {
        if (isSuspended || member.isNodeUnderMaintenance) return;

        getStats().then(() => {
            interval.current = setInterval(() => getStats(), 30000);
        });

        return () => {
            interval.current && clearInterval(interval.current);
        };
    }, [isSuspended, member.isNodeUnderMaintenance]);

    const statusLabel = isSuspended
        ? member.status === 'suspended'
            ? 'Suspended'
            : 'Connection Error'
        : member.isNodeUnderMaintenance
        ? 'Under Maintenance'
        : member.isTransferring
        ? 'Transferring'
        : member.status === 'installing'
        ? 'Installing'
        : member.status === 'restoring_backup'
        ? 'Restoring Backup'
        : !stats
        ? null
        : stats.status[0].toUpperCase() + stats.status.slice(1);

    return (
        <MemberRowBox as={Link} to={`/server/${member.id}`} className={className} $status={stats?.status}>
            <div css={tw`flex items-center col-span-12 sm:col-span-6`}>
                <div className={'icon mr-4'}>
                    <FontAwesomeIcon icon={faServer} />
                </div>
                <div className={'min-w-0'}>
                    <div css={tw`flex items-center gap-2`}>
                        <p css={tw`text-lg break-words`}>{member.name}</p>
                        <RoleBadge role={member.role} />
                    </div>
                    {!!member.description && (
                        <p css={tw`text-sm text-neutral-300 break-words line-clamp-2`}>{member.description}</p>
                    )}
                </div>
            </div>
            <div css={tw`col-span-12 sm:col-span-6 flex items-center justify-end`}>
                {!statusLabel ? (
                    <Spinner size={'small'} />
                ) : (
                    <span css={tw`bg-neutral-500 rounded-md px-2 py-1 text-neutral-100 text-xs`}>{statusLabel}</span>
                )}
            </div>
            <div className={'status-bar'} />
        </MemberRowBox>
    );
};

export default () => {
    // Areas are addressed by uuid in the client API: Pterodactyl's base model returns 'uuid'
    // from getRouteKeyName(), and the /api/client/areas routes don't override it to ':id' the way
    // the admin routes do. Passing a numeric id here resolves to no model at all.
    const { uuid } = useParams<{ uuid: string }>();
    const { clearFlashes, clearAndAddHttpError } = useFlash();

    const { data: area, error, mutate } = useSWR<Area>(`/api/client/areas/${uuid}`, () => getArea(uuid));

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'area', error });
        if (!error) clearFlashes('area');
    }, [error]);

    if (!area) {
        return (
            <PageContentBlock title={'Area'} showFlashKey={'area'}>
                <Spinner centered size={'large'} />
            </PageContentBlock>
        );
    }

    // Refresh every member's live status a moment after a power action finishes — give Wings a
    // beat to report the new state rather than refetching instantly.
    const onActionCompleted = () => setTimeout(() => mutate(), 2000);

    return (
        <PageContentBlock title={area.name} showFlashKey={'area'}>
            <div css={tw`mb-4`}>
                <h1 css={tw`text-2xl font-semibold text-neutral-50`}>{area.name}</h1>
                {!!area.description && <p css={tw`mt-1 text-sm text-neutral-400`}>{area.description}</p>}
            </div>
            <div css={tw`mb-4 flex flex-wrap items-center gap-2`}>
                <AreaPowerActions areaId={area.uuid} onCompleted={onActionCompleted} />
                <AreaCommandDialog areaId={area.uuid} onCompleted={onActionCompleted} />
            </div>
            {area.members.length > 0 ? (
                <div>
                    {area.members.map((member, index) => (
                        <MemberRow key={member.uuid} member={member} className={index > 0 ? 'mt-2' : undefined} />
                    ))}
                </div>
            ) : (
                <p css={tw`text-center text-sm text-neutral-400`}>This area has no servers assigned yet.</p>
            )}
        </PageContentBlock>
    );
};
