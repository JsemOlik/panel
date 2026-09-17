import React, { useState } from 'react';
import { PlayIcon, RefreshIcon, StopIcon } from '@heroicons/react/outline';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/elements/dialog';
import sendBulkPowerAction, { BulkPowerResult, PowerSignal } from '@/api/server/sendBulkPowerAction';
import useFlash from '@/plugins/useFlash';

interface Action {
    signal: PowerSignal;
    label: string;
    past: string;
    icon: typeof PlayIcon;
}

const actions: Action[] = [
    { signal: 'start', label: 'Start all', past: 'Start', icon: PlayIcon },
    { signal: 'restart', label: 'Restart all', past: 'Restart', icon: RefreshIcon },
    { signal: 'stop', label: 'Stop all', past: 'Stop', icon: StopIcon },
];

const servers = (count: number) => `${count} ${count === 1 ? 'server' : 'servers'}`;

const summary = ({ succeeded, skipped, failed }: BulkPowerResult, action: Action): string => {
    const parts = [`${action.past} sent to ${servers(succeeded.length)}.`];

    if (skipped.length) parts.push(`Skipped ${skipped.join(', ')} (suspended, installing or transferring).`);
    if (failed.length) parts.push(`Couldn't reach ${failed.join(', ')}.`);

    return parts.join(' ');
};

export default () => {
    const [pending, setPending] = useState<Action | null>(null);
    const [running, setRunning] = useState<PowerSignal | null>(null);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();

    const run = (action: Action) => {
        setPending(null);
        setRunning(action.signal);
        clearFlashes('dashboard');

        sendBulkPowerAction(action.signal)
            .then((result) => {
                const total = result.succeeded.length + result.skipped.length + result.failed.length;

                addFlash({
                    key: 'dashboard',
                    type: result.failed.length ? 'warning' : total === 0 ? 'info' : 'success',
                    message: total === 0 ? "You don't own any servers." : summary(result, action),
                });
            })
            .catch((error) => clearAndAddHttpError({ key: 'dashboard', error }))
            .then(() => setRunning(null));
    };

    return (
        <>
            <Dialog.Confirm
                open={!!pending}
                onClose={() => setPending(null)}
                title={pending ? `${pending.label.replace(' all', '')} all servers?` : ''}
                confirm={pending?.label}
                onConfirmed={() => pending && run(pending)}
            >
                {pending
                    ? `This will ${pending.signal} every server you own. Servers you only have access to as a subuser are not affected.`
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
                            isLoading={running === action.signal}
                            onClick={() => setPending(action)}
                        >
                            <Icon aria-hidden={'true'} />
                            {action.label}
                        </Button>
                    );
                })}
            </div>
        </>
    );
};
