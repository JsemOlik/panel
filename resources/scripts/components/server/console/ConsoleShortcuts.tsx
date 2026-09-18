import React, { useState } from 'react';
import { ServerContext } from '@/state/server';
import { ConsoleShortcut } from '@/api/server/getServer';
import { Dialog } from '@/components/elements/dialog';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Can from '@/components/elements/Can';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { buildCommand } from '@/lib/consoleShortcuts';

interface ArgumentsDialogProps {
    shortcut: ConsoleShortcut | null;
    onClose: () => void;
    onSend: (command: string) => void;
}

const ArgumentsDialog = ({ shortcut, onClose, onSend }: ArgumentsDialogProps) => {
    // The dialog is remounted each time it opens, so this starts from the argument defaults.
    const [values, setValues] = useState<Record<string, string>>(() =>
        Object.fromEntries((shortcut?.arguments || []).map((arg) => [arg.key, arg.default || '']))
    );
    const [showErrors, setShowErrors] = useState(false);

    const missing = (shortcut?.arguments || []).filter((arg) => arg.required && !(values[arg.key] || '').trim());

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!shortcut) return;

        if (missing.length > 0) {
            setShowErrors(true);
            return;
        }

        onSend(buildCommand(shortcut.command, values));
        onClose();
    };

    return (
        <Dialog
            open={!!shortcut}
            onClose={onClose}
            title={shortcut?.name}
            description={shortcut?.description || undefined}
        >
            {shortcut && (
                <form id={'console-shortcut-form'} onSubmit={submit} className={'mt-6 space-y-4'}>
                    {shortcut.arguments.map((arg, index) => {
                        const id = `shortcut_argument_${arg.key}`;
                        const invalid = showErrors && missing.includes(arg);

                        return (
                            <div key={arg.key}>
                                <Label htmlFor={id} className={'mb-2 block'}>
                                    {arg.label}
                                    {arg.required && <span className={'ml-0.5 text-red-400'}>*</span>}
                                </Label>
                                <Input
                                    id={id}
                                    autoFocus={index === 0}
                                    placeholder={arg.placeholder || undefined}
                                    value={values[arg.key] ?? ''}
                                    hasError={invalid}
                                    onChange={(e) => {
                                        // Read the value now, React 16 reuses the event before the updater runs.
                                        const value = e.currentTarget.value;
                                        setValues((v) => ({ ...v, [arg.key]: value }));
                                    }}
                                />
                                {invalid ? (
                                    <p className={'mt-1.5 text-xs text-red-400'}>This field is required.</p>
                                ) : (
                                    arg.description && (
                                        <p className={'mt-1.5 text-xs text-neutral-400'}>{arg.description}</p>
                                    )
                                )}
                            </div>
                        );
                    })}
                </form>
            )}
            <Dialog.Footer>
                <Button variant={'secondary'} onClick={onClose}>
                    Cancel
                </Button>
                <Button type={'submit'} form={'console-shortcut-form'}>
                    Send
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};

export default ({ className }: { className?: string }) => {
    const [active, setActive] = useState<ConsoleShortcut | null>(null);
    const [dialogKey, setDialogKey] = useState(0);

    const shortcuts = ServerContext.useStoreState((state) => state.server.data!.consoleShortcuts);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);

    if (shortcuts.length === 0) {
        return null;
    }

    const send = (command: string) => instance && instance.send('send command', command);
    const unavailable = !connected || status !== 'running';

    const onClick = (shortcut: ConsoleShortcut) => {
        if (shortcut.arguments.length === 0) {
            send(shortcut.command);
            return;
        }

        setDialogKey((key) => key + 1);
        setActive(shortcut);
    };

    return (
        <Can action={'control.console'}>
            <div className={cn('flex flex-wrap gap-2', className)}>
                {shortcuts.map((shortcut) => (
                    <Tooltip
                        key={shortcut.id}
                        placement={'top'}
                        content={
                            unavailable ? 'The server must be running to use shortcuts.' : shortcut.description || ''
                        }
                        disabled={!unavailable && !shortcut.description}
                    >
                        {/* Disabled buttons don't fire mouse events, so the wrapper keeps the tooltip working. */}
                        <span className={'inline-flex'}>
                            <Button variant={'secondary'} disabled={unavailable} onClick={() => onClick(shortcut)}>
                                {shortcut.name}
                                {shortcut.arguments.length > 0 && <span className={'text-neutral-400'}>…</span>}
                            </Button>
                        </span>
                    </Tooltip>
                ))}
            </div>
            <ArgumentsDialog key={dialogKey} shortcut={active} onClose={() => setActive(null)} onSend={send} />
        </Can>
    );
};
