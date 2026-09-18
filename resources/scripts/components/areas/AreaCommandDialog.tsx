import React, { useState } from 'react';
import { TerminalIcon } from '@heroicons/react/outline';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Dialog } from '@/components/elements/dialog';
import sendAreaCommand, { AreaCommandResult, AreaCommandResultServer } from '@/api/areas/sendAreaCommand';
import useFlash from '@/plugins/useFlash';

// Advisory only, not a server-side block (the real backstop is the control.console permission
// plus the activity trail — see AreaCommandService). A keyword list is inherently incomplete and
// can false-positive on legitimate chat commands ("say stop lag testing"), so this only adds an
// extra confirmation step rather than rejecting anything.
const DANGEROUS_COMMAND_PATTERN =
    /\b(stop|shutdown|restart|op|deop|ban(-ip)?|whitelist off|save-off)\b|\bkick\s+(@a|\*)/i;

const readableReason = (reason?: string): string => {
    switch (reason) {
        case 'no_permission':
            return 'no permission';
        case 'suspended':
            return 'suspended';
        case 'transferring':
            return 'transferring';
        case 'restoring_backup':
            return 'restoring backup';
        case 'installing':
            return 'installing';
        case 'node_under_maintenance':
            return 'node under maintenance';
        case 'offline':
            return 'server offline';
        case 'unreachable':
            return "couldn't reach node";
        default:
            return reason || 'unknown';
    }
};

const ResultList = ({ title, entries }: { title: string; entries: AreaCommandResultServer[] }) => {
    if (entries.length === 0) return null;

    return (
        <div className={'mt-3'}>
            <p className={'text-xs font-semibold uppercase tracking-wide text-neutral-400'}>{title}</p>
            <ul className={'mt-1 space-y-1'}>
                {entries.map((entry) => (
                    <li key={entry.id} className={'text-sm text-neutral-200'}>
                        {entry.name}
                        {entry.reason && <span className={'text-neutral-400'}> — {readableReason(entry.reason)}</span>}
                    </li>
                ))}
            </ul>
        </div>
    );
};

interface Props {
    areaId: number | string;
    // Called once a command has finished sending, successfully or not, so the caller can refresh
    // any status that might have changed (kept optional/symmetrical with AreaPowerActions).
    onCompleted?: () => void;
}

export default ({ areaId, onCompleted }: Props) => {
    const [open, setOpen] = useState(false);
    const [command, setCommand] = useState('');
    const [confirmDangerous, setConfirmDangerous] = useState(false);
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<AreaCommandResult | null>(null);
    const { clearAndAddHttpError } = useFlash();

    const isDangerous = DANGEROUS_COMMAND_PATTERN.test(command);

    const reset = () => {
        setOpen(false);
        setCommand('');
        setConfirmDangerous(false);
        setResult(null);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!command.trim() || loading) return;
        if (isDangerous && !confirmDangerous) {
            setConfirmDangerous(true);
            return;
        }

        setLoading(true);
        setResult(null);

        sendAreaCommand(areaId, command)
            .then((data) => setResult(data))
            .catch((error) => {
                clearAndAddHttpError({ key: 'area', error });
                setOpen(false);
            })
            .then(() => {
                setLoading(false);
                onCompleted?.();
            });
    };

    return (
        <>
            <Button variant={'outline'} size={'sm'} onClick={() => setOpen(true)}>
                <TerminalIcon aria-hidden={'true'} />
                Send command
            </Button>
            <Dialog
                open={open}
                onClose={reset}
                title={result ? 'Command result' : 'Send a console command'}
                description={result ? undefined : 'Sends a raw console command to every server in this area.'}
            >
                {!result ? (
                    <form id={'area-command-form'} onSubmit={submit} className={'mt-4'}>
                        <Textarea
                            autoFocus
                            rows={3}
                            placeholder={'say Lunch in 10 minutes'}
                            value={command}
                            onChange={(e) => {
                                setCommand(e.currentTarget.value);
                                setConfirmDangerous(false);
                            }}
                        />
                        {isDangerous && confirmDangerous && (
                            <p className={'mt-2 text-sm text-yellow-400'}>
                                This looks like it could disrupt every server in this area — send again to confirm.
                            </p>
                        )}
                    </form>
                ) : (
                    <div className={'mt-4'}>
                        <p className={'text-sm text-neutral-300'}>
                            Sent <span className={'font-mono'}>{result.command}</span> to {result.succeeded.length}{' '}
                            {result.succeeded.length === 1 ? 'server' : 'servers'}.
                        </p>
                        <ResultList title={'Succeeded'} entries={result.succeeded} />
                        <ResultList title={'Skipped'} entries={result.skipped} />
                        <ResultList title={'Failed'} entries={result.failed} />
                    </div>
                )}
                <Dialog.Footer>
                    <Button variant={'secondary'} onClick={reset}>
                        {result ? 'Close' : 'Cancel'}
                    </Button>
                    {!result && (
                        <Button
                            type={'submit'}
                            form={'area-command-form'}
                            isLoading={loading}
                            disabled={!command.trim()}
                        >
                            {isDangerous && !confirmDangerous ? 'Review & send' : 'Send'}
                        </Button>
                    )}
                </Dialog.Footer>
            </Dialog>
        </>
    );
};
