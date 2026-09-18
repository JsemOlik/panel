import React, { useEffect, useRef, useState } from 'react';
import { mutate } from 'swr';
import { useServerPlayers } from '@/api/server/players/getServerPlayers';
import { useServerPlayerSessions } from '@/api/server/players/getServerPlayerSessions';
import sendPlayerAction, { PlayerAction } from '@/api/server/players/sendPlayerAction';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { useFlashKey } from '@/plugins/useFlash';
import { useServerSWRKey } from '@/plugins/useSWRKey';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import Can from '@/components/elements/Can';
import Input from '@/components/elements/Input';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/elements/dialog';
import { ServerContext } from '@/state/server';
import { ServerPlayer } from '@definitions/players';
import { format, formatDistanceToNow } from 'date-fns';

const StatusDot = ({ online }: { online: boolean }) => (
    <span
        className={`inline-block h-2.5 w-2.5 rounded-full mr-2 shrink-0 ${online ? 'bg-green-500' : 'bg-neutral-600'}`}
    />
);

/**
 * The player's Minecraft head, rendered from their username.
 *
 * This is the one thing on the page that leaves the network: the avatar service is asked for a
 * head by username, so staff browsers disclose the usernames of children playing here to a third
 * party. The names are already public on any server list, but if that is not acceptable the fix
 * is to drop this component — nothing else depends on it.
 *
 * An offline player is dimmed rather than given a different image, and a name the service has no
 * skin for simply falls back to the initial: a broken image icon next to a child's name reads as
 * an error in the tool rather than an absent skin.
 */
const PlayerHead = ({ name, online }: { name: string; online: boolean }) => {
    const [failed, setFailed] = useState(false);

    // Reset when the row is reused for a different player, or a previously failed lookup would
    // keep showing the initial for whoever lands in this slot next.
    useEffect(() => setFailed(false), [name]);

    if (failed) {
        return (
            <span
                className={`inline-flex items-center justify-center h-8 w-8 mr-3 shrink-0 rounded bg-neutral-700 text-xs font-medium text-neutral-300 ${
                    online ? '' : 'opacity-50'
                }`}
                aria-hidden
            >
                {name.slice(0, 1).toUpperCase()}
            </span>
        );
    }

    return (
        <img
            src={`https://mc-heads.net/avatar/${encodeURIComponent(name)}/64`}
            alt={''}
            aria-hidden
            loading={'lazy'}
            onError={() => setFailed(true)}
            className={`h-8 w-8 mr-3 shrink-0 rounded ${online ? '' : 'opacity-50'}`}
            // Heads are 8x8 pixel art upscaled; smoothing them turns the face to mush.
            style={{ imageRendering: 'pixelated' }}
        />
    );
};

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

/**
 * Copy for each moderation action, kept in one place so the dialog, its button and the text field
 * cannot drift apart. `destructive` drives the red confirm button and the warning icon: a kick is
 * an interruption, a ban is not something to fire off by accident on a misread row.
 */
const ACTION_COPY: Record<
    PlayerAction,
    {
        title: string;
        label: string;
        confirm: string;
        required: boolean;
        destructive: boolean;
        help: string;
        // Shown after a 204 from Wings. A 204 only means the daemon accepted the command string,
        // not that it did anything in-game, so this must never claim more than "sent" -- staff
        // still have to look at the server to know a kick or ban actually landed.
        success: (player: string) => string;
    }
> = {
    message: {
        title: 'Send a private message',
        label: 'Message',
        confirm: 'Send message',
        required: true,
        destructive: false,
        help: 'Sent with /tell, so only this player sees it.',
        success: (player) => `Message sent to ${player}.`,
    },
    kick: {
        title: 'Kick from the server',
        label: 'Reason (optional)',
        confirm: 'Kick player',
        required: false,
        destructive: true,
        help: 'Disconnects the player. They can rejoin immediately. The reason is shown to them.',
        success: (player) => `Kick command sent for ${player}. Confirm in-game that it took effect.`,
    },
    ban: {
        title: 'Ban from the server',
        label: 'Reason (optional)',
        confirm: 'Ban player',
        required: false,
        destructive: true,
        help: 'Adds the player to the server ban list. Undo this in-game with /pardon.',
        success: (player) => `Ban command sent for ${player}. Confirm in-game that it took effect.`,
    },
};

/**
 * Collects the text for one moderation action and sends it.
 *
 * The command itself is built server-side from the action and the player's roster row — this only
 * ever sends the action name and free text, so there is no place here where a stray newline could
 * turn one action into two console commands.
 */
const PlayerActionDialog = ({
    player,
    action,
    onClose,
}: {
    player: string;
    action: PlayerAction | null;
    onClose: () => void;
}) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey(`server:players:${player}`);
    // Success feedback belongs on the list itself (via `server:players`), not the per-dialog key
    // above -- the dialog is about to close, so a flash scoped to it would never be seen.
    const { addFlash } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);
    const playersKey = useServerSWRKey(['players', {}]);
    const [text, setText] = useState('');
    // Only used for the ban confirmation step below; see the name-match requirement in `submit`.
    const [confirmName, setConfirmName] = useState('');
    const [submitting, setSubmitting] = useState(false);

    // This component is always rendered by the row (it just returns null while there is no
    // action), so it never actually unmounts today -- but nothing guarantees that stays true, and
    // guarding here costs nothing.
    const mountedRef = useRef(true);
    useEffect(() => {
        mountedRef.current = true;
        return () => {
            mountedRef.current = false;
        };
    }, []);

    // Never carry the previous action's text into the next dialog — a kick reason sent as a
    // private message would be an unpleasant surprise. Flashes are stored in a global,
    // string-keyed store that survives this component's open/close cycles, so an error from a
    // previous attempt has to be cleared here too or it would still be showing next time this
    // player's dialog is reopened.
    useEffect(() => {
        setText('');
        setConfirmName('');
        clearFlashes();
    }, [action, player]);

    if (action === null) {
        return null;
    }

    const copy = ACTION_COPY[action];
    // The roster re-sorts online-first on every 5s poll, so a row a moderator lined up a click on
    // can shift under the cursor before the click lands. For a ban specifically -- the one action
    // here with a real, lasting consequence for a real person -- the dialog showing the player's
    // name is not enough to catch that: typing a reason (or nothing at all) doesn't require
    // reading it. Requiring the name to be typed out forces it to be read and registered, and as a
    // side effect nothing can be pre-filled by a stray keypress or paste.
    const nameConfirmed = action !== 'ban' || confirmName.trim() === player;
    // A misclick landing on Ban is still just one open dialog, not a banned player, as long as
    // Enter can't drive it home from there. Kick and ban both keep their confirm button behind an
    // explicit click; only the non-destructive message action gets the Enter-to-send convenience.
    const fieldId = `player-action-text-${player}-${action}`;
    const confirmFieldId = `player-action-confirm-${player}-${action}`;

    const submit = () => {
        setSubmitting(true);
        clearFlashes();

        sendPlayerAction(uuid, player, action, text.trim() || undefined)
            .then(() => {
                // A 204 only means Wings accepted the command string; revalidate so a successful
                // kick visibly moves the player to offline instead of waiting up to 5s for the
                // next poll, and say only that much in the flash.
                mutate(playersKey);
                addFlash({ key: 'server:players', type: 'success', message: copy.success(player) });
                onClose();
            })
            .catch((error) => {
                if (mountedRef.current) clearAndAddHttpError(error);
            })
            .finally(() => {
                if (mountedRef.current) setSubmitting(false);
            });
    };

    return (
        <Dialog open onClose={onClose} title={`${copy.title} — ${player}`}>
            {copy.destructive && <Dialog.Icon type={'danger'} position={'container'} />}
            <FlashMessageRender byKey={`server:players:${player}`} className={'mb-4'} />
            <p className={'text-sm text-neutral-400 mb-3'}>{copy.help}</p>
            <label className={'block text-xs uppercase tracking-wide text-neutral-400 mb-1'} htmlFor={fieldId}>
                {copy.label}
            </label>
            <Input
                id={fieldId}
                autoFocus
                value={text}
                maxLength={256}
                disabled={submitting}
                onChange={(e) => setText(e.currentTarget.value)}
                onKeyDown={(e) => {
                    // Destructive actions are confirmed by clicking the (also disabled-until-ready)
                    // button below, never by Enter -- that is what turns "Ban" then a stray Enter
                    // into an instant ban.
                    if (e.key === 'Enter' && !copy.destructive && !(copy.required && text.trim().length === 0)) {
                        submit();
                    }
                }}
            />
            {action === 'ban' && (
                <div className={'mt-3'}>
                    <label
                        className={'block text-xs uppercase tracking-wide text-neutral-400 mb-1'}
                        htmlFor={confirmFieldId}
                    >
                        Type &ldquo;{player}&rdquo; to confirm
                    </label>
                    <Input
                        id={confirmFieldId}
                        value={confirmName}
                        maxLength={256}
                        disabled={submitting}
                        onChange={(e) => setConfirmName(e.currentTarget.value)}
                    />
                </div>
            )}
            <Dialog.Footer>
                <Button variant={'secondary'} disabled={submitting} onClick={onClose}>
                    Cancel
                </Button>
                <Button
                    variant={copy.destructive ? 'destructive' : 'default'}
                    disabled={submitting || (copy.required && text.trim().length === 0) || !nameConfirmed}
                    onClick={submit}
                >
                    {copy.confirm}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};

const PlayerRow = ({ player }: { player: ServerPlayer }) => {
    const [expanded, setExpanded] = useState(false);
    const [action, setAction] = useState<PlayerAction | null>(null);
    const online = player.status === 'online';

    return (
        <div className={'px-4 py-3'}>
            <PlayerActionDialog player={player.name} action={action} onClose={() => setAction(null)} />
            <div className={'flex items-center gap-3'}>
                <button
                    type={'button'}
                    className={'flex items-center flex-1 min-w-0 text-left'}
                    onClick={() => setExpanded((value) => !value)}
                >
                    <PlayerHead name={player.name} online={online} />
                    <StatusDot online={online} />
                    <span className={'text-neutral-200 font-medium truncate'}>{player.name}</span>
                    <span className={'ml-auto pl-3 text-xs text-neutral-500 whitespace-nowrap'}>
                        {online
                            ? player.joinedAt
                                ? `online since ${formatDistanceToNow(player.joinedAt, { addSuffix: true })}`
                                : 'online'
                            : player.lastSeenAt
                            ? `last seen ${formatDistanceToNow(player.lastSeenAt, { addSuffix: true })}`
                            : 'offline'}
                    </span>
                </button>
                <Can action={'players.moderate'}>
                    <div className={'flex items-center gap-1 shrink-0'}>
                        {/* Messaging or kicking someone who is not connected does nothing, so those
                            are hidden rather than shown failing. A ban is still meaningful while
                            they are away — often that is exactly when it gets decided. */}
                        {online && (
                            <>
                                <Button size={'sm'} variant={'ghost'} onClick={() => setAction('message')}>
                                    Message
                                </Button>
                                <Button size={'sm'} variant={'ghost'} onClick={() => setAction('kick')}>
                                    Kick
                                </Button>
                            </>
                        )}
                        <Button size={'sm'} variant={'destructive-ghost'} onClick={() => setAction('ban')}>
                            Ban
                        </Button>
                    </div>
                </Can>
            </div>
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
