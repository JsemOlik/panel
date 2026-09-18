import React, { useEffect, useState } from 'react';
import { useServerPlayers } from '@/api/server/players/getServerPlayers';
import { useServerPlayerSessions } from '@/api/server/players/getServerPlayerSessions';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { useFlashKey } from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import { ServerPlayer } from '@definitions/players';
import { format, formatDistanceToNow } from 'date-fns';

const StatusDot = ({ online }: { online: boolean }) => (
    <span
        className={`inline-block h-2.5 w-2.5 rounded-full mr-2 shrink-0 ${online ? 'bg-green-500' : 'bg-neutral-600'}`}
    />
);

const PlayerSessionHistory = ({ player }: { player: string }) => {
    const { data, isValidating } = useServerPlayerSessions(player, 1, { revalidateOnMount: true });

    if (isValidating && !data) {
        return <Spinner size={'small'} />;
    }

    if (!data?.items.length) {
        return <p className={'text-xs text-neutral-500'}>No recorded join/leave activity for this player yet.</p>;
    }

    return (
        <ul className={'space-y-1'}>
            {data.items.map((session) => (
                <li key={session.id} className={'text-xs font-mono flex gap-2'}>
                    <span className={session.event === 'join' ? 'text-green-400' : 'text-neutral-500'}>
                        {session.event === 'join' ? 'Joined' : 'Left'}
                    </span>
                    <span className={'text-neutral-400'}>{format(session.occurredAt, 'yyyy-MM-dd HH:mm:ss')}</span>
                </li>
            ))}
        </ul>
    );
};

const PlayerRow = ({ player }: { player: ServerPlayer }) => {
    const [expanded, setExpanded] = useState(false);
    const online = player.status === 'online';

    return (
        <div className={'px-4 py-3'}>
            <button
                type={'button'}
                className={'flex items-center w-full text-left'}
                onClick={() => setExpanded((value) => !value)}
            >
                <StatusDot online={online} />
                <span className={'text-neutral-200 font-medium'}>{player.name}</span>
                <span className={'ml-auto text-xs text-neutral-500'}>
                    {online
                        ? player.joinedAt
                            ? `online since ${formatDistanceToNow(player.joinedAt, { addSuffix: true })}`
                            : 'online'
                        : player.lastSeenAt
                        ? `last seen ${formatDistanceToNow(player.lastSeenAt, { addSuffix: true })}`
                        : 'offline'}
                </span>
            </button>
            {expanded && (
                <div className={'mt-2 ml-[1.125rem] pl-2 border-l border-neutral-700'}>
                    <PlayerSessionHistory player={player.name} />
                </div>
            )}
        </div>
    );
};

export default () => {
    const { clearAndAddHttpError } = useFlashKey('server:players');
    const { data, isValidating, error } = useServerPlayers();

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    const online = (data || []).filter((p) => p.status === 'online');
    const offline = (data || []).filter((p) => p.status === 'offline');

    return (
        <ServerContentBlock title={'Players'}>
            <FlashMessageRender byKey={'server:players'} />
            <p className={'text-sm text-neutral-400 mb-4'}>
                Player presence derived from captured console output. Join/leave detection is best-effort — a heavily
                modified server or plugin can change the log format enough that a player never appears here, even though
                their raw console lines are still captured.
            </p>
            {!data && isValidating ? (
                <Spinner centered />
            ) : !data?.length ? (
                <p className={'text-sm text-center text-neutral-400'}>
                    No players have been observed on this server yet.
                </p>
            ) : (
                <div className={'bg-neutral-800 rounded-lg divide-y divide-neutral-700'}>
                    {online.length > 0 && (
                        <div className={'px-4 py-2 text-2xs uppercase tracking-wide text-neutral-500'}>
                            Online — {online.length}
                        </div>
                    )}
                    {online.map((player) => (
                        <PlayerRow key={player.id} player={player} />
                    ))}
                    {offline.length > 0 && (
                        <div className={'px-4 py-2 text-2xs uppercase tracking-wide text-neutral-500'}>
                            Offline — {offline.length}
                        </div>
                    )}
                    {offline.map((player) => (
                        <PlayerRow key={player.id} player={player} />
                    ))}
                </div>
            )}
        </ServerContentBlock>
    );
};
