import React, { useEffect, useRef, useState } from 'react';
import { PlayIcon, RefreshIcon, StopIcon } from '@heroicons/react/outline';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/elements/dialog';
import sendAreaPowerAction, { AreaPowerSignal } from '@/api/areas/sendAreaPowerAction';
import { PowerActionResult } from '@/api/definitions/power';
import useFlash from '@/plugins/useFlash';

interface Action {
    signal: AreaPowerSignal;
    label: string;
    past: string;
    icon: typeof PlayIcon;
}

const actions: Action[] = [
    { signal: 'start', label: 'Start area', past: 'Start', icon: PlayIcon },
    { signal: 'restart', label: 'Restart area', past: 'Restart', icon: RefreshIcon },
    { signal: 'stop', label: 'Stop area', past: 'Stop', icon: StopIcon },
];

const servers = (count: number) => `${count} ${count === 1 ? 'server' : 'servers'}`;

const summary = (result: PowerActionResult<AreaPowerSignal>, action: Action): string => {
    const { succeeded, skipped, failed } = result;
    const parts = [`${action.past} sent to ${servers(succeeded.length)}.`];

    if (skipped.length) parts.push(`Skipped ${skipped.join(', ')}.`);
    if (failed.length) parts.push(`Couldn't reach ${failed.join(', ')}.`);

    return parts.join(' ');
};

interface Props {
    areaId: number | string;
    // Called once an action has finished (successfully or not) so the caller can refresh the
    // area's member statuses.
    onCompleted?: () => void;
}

// A sequenced area action (proxy last up, first down) can take 60-90 seconds server-side since
// the backend waits for members to reach a stable state before acting on the proxy. This ticks
// a counter while the request is outstanding so the UI clearly communicates that it's working
// rather than appearing hung.
const useElapsedSeconds = (active: boolean): number => {
    const [seconds, setSeconds] = useState(0);
    const interval = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        if (!active) {
            setSeconds(0);
            if (interval.current) clearInterval(interval.current);
            return;
        }

        interval.current = setInterval(() => setSeconds((value) => value + 1), 1000);

        return () => {
            if (interval.current) clearInterval(interval.current);
        };
    }, [active]);

    return seconds;
};

export default ({ areaId, onCompleted }: Props) => {
    const [pending, setPending] = useState<Action | null>(null);
    const [running, setRunning] = useState<Action | null>(null);
    const elapsed = useElapsedSeconds(!!running);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();

    const run = (action: Action) => {
        setPending(null);
        setRunning(action);
        clearFlashes('area');

        sendAreaPowerAction(areaId, action.signal)
            .then((result) => {
                addFlash({
                    key: 'area',
                    type: result.failed.length ? 'warning' : 'success',
                    message: summary(result, action),
                });
            })
            .catch((error) => clearAndAddHttpError({ key: 'area', error }))
            .then(() => {
                setRunning(null);
                onCompleted?.();
            });
    };

    return (
        <>
            <Dialog.Confirm
                open={!!pending}
                onClose={() => setPending(null)}
                title={pending ? `${pending.label.replace(' area', '')} this area?` : ''}
                confirm={pending?.label}
                onConfirmed={() => pending && run(pending)}
            >
                {pending
                    ? `This will ${pending.signal} every server in this area in the correct order (the proxy is ${
                          pending.signal === 'start' ? 'started last' : 'stopped first'
                      }). It can take up to a minute or two to finish.`
                    : ''}
            </Dialog.Confirm>
            <div className={'flex flex-wrap items-center gap-2'}>
                {actions.map((action) => {
                    const Icon = action.icon;

                    return (
                        <Button
                            key={action.signal}
                            variant={'outline'}
                            size={'sm'}
                            disabled={!!running}
                            isLoading={running?.signal === action.signal}
                            onClick={() => setPending(action)}
                        >
                            <Icon aria-hidden={'true'} />
                            {action.label}
                        </Button>
                    );
                })}
                {running && (
                    <span className={'text-sm text-neutral-400'}>
                        Sequencing {running.signal}&hellip; this can take up to a couple of minutes ({elapsed}s
                        elapsed). The proxy is handled {running.signal === 'start' ? 'last' : 'first'}.
                    </span>
                )}
            </div>
        </>
    );
};
